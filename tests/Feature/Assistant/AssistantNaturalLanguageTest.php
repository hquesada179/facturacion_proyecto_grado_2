<?php

namespace Tests\Feature\Assistant;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * IA 2.0-C: equivalent natural phrasings must resolve to the same
 * underlying tool/component — never hundreds of literal regexes, but a
 * small synonym-canonicalization table (AssistantMessageNormalizer) plus
 * the existing deterministic dispatcher. See the IA 2.0-C report for why
 * a deterministic dispatcher was kept over provider-side function-calling.
 */
class AssistantNaturalLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function administrador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->administrador()->create();
    }

    private function issuedInvoice(User $user, array $overrides = []): Invoice
    {
        NumberingResolution::factory()->for($user->company)->create([
            'prefix' => 'FV', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1,
        ]);

        $customer = Customer::factory()->for($user->company)->create(array_merge(['status' => 'active'], $overrides));
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000',
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh()->load('customer');
    }

    private function draftInvoice(User $user): Invoice
    {
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        return $invoice;
    }

    private function ask(User $user, string $message): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => $message,
            'screen' => 'dashboard',
        ]);
    }

    // --- IVA synonyms --------------------------------------------------------

    public function test_iva_synonyms_all_trigger_the_billing_summary(): void
    {
        $user = $this->facturador();
        $this->issuedInvoice($user);

        $phrasings = [
            '¿Cuánto IVA he generado?',
            '¿Cuánto IVA llevo?',
            '¿Cuánto tengo de IVA?',
            'Dime el IVA generado.',
            '¿Qué IVA se ha causado?',
            'IVA generado este mes.',
        ];

        foreach ($phrasings as $phrasing) {
            $response = $this->ask($user, $phrasing)->assertOk();
            $summary = collect($response->json('components'))->firstWhere('type', 'metric_summary');

            $this->assertNotNull($summary, "Phrasing failed to trigger billing summary: {$phrasing}");
            $this->assertNotNull(
                collect($summary['items'])->firstWhere('label', 'IVA / impuestos'),
                "Phrasing did not produce an IVA line: {$phrasing}"
            );
        }
    }

    // --- Facturas synonyms ---------------------------------------------------

    public function test_facturas_de_cliente_synonyms_all_return_the_same_invoices(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoice($user, ['name' => 'Comercializadora Kata SAS']);

        $phrasings = [
            'Muéstrame las facturas de kata.',
            'Dame las facturas de kata.',
            'Quiero ver lo facturado a kata.',
            '¿Qué facturas tiene kata?',
        ];

        foreach ($phrasings as $phrasing) {
            $response = $this->ask($user, $phrasing)->assertOk();
            $list = collect($response->json('components'))->firstWhere('type', 'invoice_list');

            $this->assertNotNull($list, "Phrasing failed to return an invoice_list: {$phrasing}");
            $this->assertSame($invoice->number, $list['items'][0]['number'], "Phrasing returned the wrong invoice: {$phrasing}");
        }
    }

    // --- Borradores synonyms --------------------------------------------------

    public function test_borradores_synonyms_both_return_pending_drafts(): void
    {
        $user = $this->facturador();
        $draft = $this->draftInvoice($user);

        foreach (['Muéstrame mis borradores pendientes.', 'Dame los borradores de este mes.'] as $phrasing) {
            $response = $this->ask($user, $phrasing)->assertOk();
            $list = collect($response->json('components'))->firstWhere('type', 'invoice_list');

            $this->assertNotNull($list, "Phrasing failed to return drafts: {$phrasing}");
        }
    }

    public function test_every_reasonable_borradores_phrasing_resolves_to_the_actual_drafts(): void
    {
        $user = $this->facturador();
        $this->draftInvoice($user);

        $phrasings = [
            'Borradores de este mes.',
            'Dame los borradores de este mes.',
            'Muéstrame borradores del mes.',
            'Borradores pendientes este mes.',
            'Dame los borradores.',
        ];

        foreach ($phrasings as $phrasing) {
            $response = $this->ask($user, $phrasing)->assertOk();
            $list = collect($response->json('components'))->firstWhere('type', 'invoice_list');

            $this->assertNotNull($list, "Phrasing failed to return drafts: {$phrasing}");
            $this->assertNotEmpty($list['items'], "Phrasing returned an empty list: {$phrasing}");
        }
    }

    // --- Notas crédito synonyms -----------------------------------------------

    public function test_notas_credito_de_cliente_synonym_returns_the_right_credit_notes(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoice($user, ['name' => 'Comercializadora Kata SAS']);
        $item = $invoice->items()->firstOrFail();

        NumberingResolution::factory()->for($user->company)->creditNote()->create([
            'prefix' => 'NC', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1,
        ]);
        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_PARTIAL_RETURN',
            'reason_text' => 'Ajuste de prueba',
            'items' => [$item->id => ['quantity' => '1']],
        ]);
        $creditNote = $invoice->creditNotes()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));

        $response = $this->ask($user, 'Dame las notas crédito de kata.')->assertOk();
        $list = collect($response->json('components'))->firstWhere('type', 'credit_note_list');

        $this->assertNotNull($list);
        $this->assertSame($invoice->number, $list['items'][0]['invoice_number']);
    }

    // --- Trazabilidad synonyms -------------------------------------------------

    public function test_trazabilidad_synonyms_both_resolve_the_same_invoice(): void
    {
        $user = $this->administrador();
        $invoice = $this->issuedInvoice($user);

        foreach (['¿Qué pasó con '.$invoice->number.'?', 'Resume la trazabilidad de '.$invoice->number.'.'] as $phrasing) {
            $response = $this->ask($user, $phrasing)->assertOk();
            $summary = collect($response->json('components'))->firstWhere('type', 'traceability_summary');

            $this->assertNotNull($summary, "Phrasing failed to resolve traceability: {$phrasing}");
            $this->assertSame($invoice->number, $summary['invoice_number']);
        }
    }

    // --- Combined filters ------------------------------------------------------

    public function test_combined_customer_status_and_date_filters_resolve_together(): void
    {
        $user = $this->facturador();
        $customer = Customer::factory()->for($user->company)->create(['name' => 'Comercializadora Kata SAS', 'status' => 'active']);
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1]);

        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);
        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        $response = $this->ask($user, 'Facturas anuladas de kata este mes.')->assertOk();

        // No voided invoice exists for kata in this fixture — the
        // combined filter must come back empty/absent, never a
        // non-voided invoice of hers.
        $list = collect($response->json('components'))->firstWhere('type', 'invoice_list');

        if ($list !== null) {
            foreach ($list['items'] as $item) {
                $this->assertSame('voided', $item['status']);
            }
        }
    }

    public function test_product_tax_filter_combined_with_natural_phrasing(): void
    {
        $user = $this->facturador();
        $tax = \App\Models\Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['name' => 'Servicio con IVA alto']);
        $product->taxes()->attach($tax->id);

        $response = $this->ask($user, 'Dame los productos con IVA 19%.')->assertOk();

        $this->assertStringContainsString('Servicio con IVA alto', $response->json('message'));
    }

    // --- IVA tax-rate phrasing variants ---------------------------------------

    public function test_every_reasonable_iva_19_percent_phrasing_finds_the_same_product(): void
    {
        $user = $this->facturador();
        $tax = \App\Models\Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['name' => 'Servicio con IVA alto']);
        $product->taxes()->attach($tax->id);

        $phrasings = [
            'Muéstrame los productos con IVA 19%.',
            'Muéstrame los productos con IVA al 19%.',
            'Muéstrame los productos que tengan IVA 19%.',
            'Muéstrame los productos gravados con IVA 19%.',
            'Muéstrame los productos con impuesto IVA 19%.',
        ];

        foreach ($phrasings as $phrasing) {
            $response = $this->ask($user, $phrasing)->assertOk();
            $list = collect($response->json('components'))->firstWhere('type', 'product_list');

            $this->assertNotNull($list, "Phrasing failed to return a product_list: {$phrasing}");
            $this->assertNotEmpty($list['items'], "Phrasing returned an empty list: {$phrasing}");
            $this->assertSame('Servicio con IVA alto', $list['items'][0]['name'], "Phrasing returned the wrong product: {$phrasing}");
        }
    }

    // --- Typo tolerance ----------------------------------------------------

    public function test_cuantao_iva_llevo_is_corrected_and_still_answers(): void
    {
        $user = $this->facturador();
        $this->issuedInvoice($user);

        $response = $this->ask($user, 'cuantao iva llevo')->assertOk();

        $summary = collect($response->json('components'))->firstWhere('type', 'metric_summary');
        $this->assertNotNull($summary);
    }

    public function test_a_typo_in_an_unrelated_sentence_never_breaks_the_response(): void
    {
        $user = $this->facturador();

        $this->ask($user, 'Holaa, buenso dias')->assertOk();
    }

    public function test_factruas_anuladas_mild_typo_is_corrected_and_dispatches_normally(): void
    {
        $user = $this->facturador();
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $voided = $this->issuedInvoiceForCustomer($user, $customer);
        $this->voidInvoice($user, $voided);

        $response = $this->ask($user, 'Muéstrame las factruas anuladas.')->assertOk();

        $list = collect($response->json('components'))->firstWhere('type', 'invoice_list');
        $this->assertNotNull($list);
        $this->assertSame('voided', $list['items'][0]['status']);
    }

    public function test_clietnes_activos_mild_typo_is_corrected_and_dispatches_normally(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Comercializadora Kata SAS', 'status' => 'active']);

        $response = $this->ask($user, 'Muéstrame los clietnes activos.')->assertOk();

        $list = collect($response->json('components'))->firstWhere('type', 'customer_list');
        $this->assertNotNull($list);
    }

    // --- Confidence tiers: mild typo (auto-correct) vs strong typo (ask) -----

    public function test_a_mild_typo_of_borradores_next_to_a_date_still_applies_the_status_filter(): void
    {
        // "borradpres" is a single adjacent-letter transposition away from
        // "borradores" (distance 1, the "safe" tier) — it must still
        // scope the search to drafts, not silently fall back to every
        // invoice this month regardless of status.
        $user = $this->facturador();
        $this->draftInvoice($user);
        $issued = $this->issuedInvoice($user);

        $response = $this->ask($user, 'Dame los borradpres de este mes.')->assertOk();

        $list = collect($response->json('components'))->firstWhere('type', 'invoice_list');
        $this->assertNotNull($list);

        foreach ($list['items'] as $item) {
            $this->assertSame('draft', $item['status']);
            $this->assertNotSame($issued->number, $item['number']);
        }
    }

    public function test_a_strong_typo_of_borradores_next_to_a_date_asks_for_clarification_instead_of_guessing(): void
    {
        // "barradires" is two edits away from "borradores" — too far for
        // the safe-correction tier. Next to "de este mes" (a real date
        // phrase on its own), the dispatcher must NOT silently drop the
        // status word and return every invoice that month as if it had
        // understood "borradores" — see AiAssistantService::
        // ambiguousStatusWord().
        $user = $this->facturador();
        $this->draftInvoice($user);
        $this->issuedInvoice($user);

        $response = $this->ask($user, 'Puedes darme los barradires de este mes.')->assertOk();

        $this->assertSame([], $response->json('components'));
        $this->assertFalse((bool) $response->json('requires_confirmation'));
        $this->assertStringContainsString('¿Te refieres a', $response->json('message'));
        $this->assertStringContainsString('borradores', $response->json('message'));
    }

    public function test_a_strong_typo_never_falsely_claims_zero_results_were_found(): void
    {
        // The honest answer to "I'm not confident what word you meant" is
        // a clarifying question — never a sentence shaped like "I ran
        // your query and found nothing" (which implies the system DID
        // understand and the data genuinely isn't there).
        $user = $this->facturador();
        $this->issuedInvoice($user);

        $response = $this->ask($user, 'Puedes darme los barradires de este mes.')->assertOk();

        $this->assertStringNotContainsString('No encontré', $response->json('message'));
        $this->assertStringNotContainsString('0 ', $response->json('message'));
    }

    private function voidInvoice(User $user, Invoice $invoice): void
    {
        if (NumberingResolution::query()->where('company_id', $user->company_id)->where('prefix', 'NC')->doesntExist()) {
            NumberingResolution::factory()->for($user->company)->creditNote()->create([
                'prefix' => 'NC', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1,
            ]);
        }

        $item = $invoice->items()->firstOrFail();
        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_TOTAL_VOID',
            'reason_text' => 'Anulación de prueba',
            'items' => [$item->id => ['quantity' => (string) $item->quantity]],
        ]);
        $creditNote = $invoice->creditNotes()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));
    }

    private function issuedInvoiceForCustomer(User $user, Customer $customer): Invoice
    {
        if (NumberingResolution::query()->where('company_id', $user->company_id)->where('prefix', 'FV')->doesntExist()) {
            NumberingResolution::factory()->for($user->company)->create([
                'prefix' => 'FV', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1,
            ]);
        }

        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000',
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh();
    }
}
