<?php

namespace Tests\Feature\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceItem;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\CreditNotes\CreditNoteCalculator;
use App\Services\CreditNotes\CreditNoteValidationService;
use App\Services\CreditNotes\IssueCreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreditNoteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function contador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->contador()->create();
    }

    private function auditor(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->auditor()->create();
    }

    private function issuedInvoiceFor(User $user, string $quantity = '2'): Invoice
    {
        NumberingResolution::factory()->for($user->company)->create([
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => 1,
        ]);

        $invoice = new Invoice(['user_id' => $user->id, 'currency' => 'COP', 'payment_type' => 'contado']);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create([
            'sku' => 'PS-CN',
            'name' => 'Servicio acreditable',
            'price' => '100000.00',
        ]);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => '100000',
            'discount_percent' => '0',
            'tax_ids' => [$tax->id],
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh();
    }

    private function createCreditNote(User $user, Invoice $invoice, string $quantity, string $reason = 'PROTO_PARTIAL_RETURN'): CreditNote
    {
        $item = $invoice->items()->firstOrFail();

        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => $reason,
            'reason_text' => 'Ajuste de prueba',
            'items' => [
                $item->id => ['quantity' => $quantity],
            ],
        ])->assertRedirect();

        return CreditNote::latest('id')->firstOrFail();
    }

    private function createCreditNoteResolution(User $user, int $current = 1): NumberingResolution
    {
        return NumberingResolution::factory()->for($user->company)->creditNote()->create([
            'prefix' => 'NC',
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => $current,
        ]);
    }

    public function test_creates_a_credit_note_draft_from_an_issued_invoice(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user);

        $creditNote = $this->createCreditNote($user, $invoice, '1');

        $this->assertSame(CreditNoteStatus::Draft, $creditNote->status);
        $this->assertSame($invoice->id, $creditNote->invoice_id);
        $this->assertSame('119000.00', (string) $creditNote->total);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'credit_note_draft_created',
        ]);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'credit_note_item_added',
        ]);
    }

    public function test_partial_credit_note_leaves_invoice_partially_credited(): void
    {
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '1');

        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote))->assertRedirect();
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote))->assertRedirect(route('credit-notes.show', $creditNote));

        $this->assertSame(CreditNoteStatus::Issued, $creditNote->refresh()->status);
        $this->assertSame(InvoiceStatus::PartiallyCredited, $invoice->refresh()->status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'invoice_partially_credited',
        ]);
    }

    public function test_total_credit_note_voids_invoice(): void
    {
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '2', 'PROTO_TOTAL_VOID');

        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));

        $this->assertSame(InvoiceStatus::Voided, $invoice->refresh()->status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'invoice_voided',
        ]);
    }

    public function test_remaining_balance_can_void_a_partially_credited_invoice(): void
    {
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $first = $this->createCreditNote($user, $invoice, '1');
        $this->actingAs($user)->post(route('credit-notes.validate', $first));
        $this->actingAs($user)->post(route('credit-notes.issue', $first));
        $this->assertSame(InvoiceStatus::PartiallyCredited, $invoice->refresh()->status);

        $second = $this->createCreditNote($user, $invoice->refresh(), '1', 'PROTO_TOTAL_VOID');
        $this->actingAs($user)->post(route('credit-notes.validate', $second));
        $this->actingAs($user)->post(route('credit-notes.issue', $second));

        $this->assertSame(InvoiceStatus::Voided, $invoice->refresh()->status);
    }

    public function test_cannot_credit_more_quantity_than_available(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user);
        $item = $invoice->items()->firstOrFail();

        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_PARTIAL_RETURN',
            'reason_text' => 'Exceso',
            'items' => [$item->id => ['quantity' => '3']],
        ])->assertSessionHasErrors();
    }

    public function test_cannot_credit_more_value_than_original_line(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '1');
        $item = $creditNote->items()->firstOrFail();
        $item->forceFill(['unit_price' => '999999.00'])->save();
        app(CreditNoteCalculator::class)->recalculate($creditNote);

        $errors = app(CreditNoteValidationService::class)->validateDraft($creditNote->refresh());

        $this->assertArrayHasKey('item_value_'.$item->id, $errors);
    }

    public function test_cannot_create_credit_note_for_non_issued_invoice(): void
    {
        $user = $this->facturador();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_PARTIAL_RETURN',
            'reason_text' => 'No emitida',
            'items' => [1 => ['quantity' => '1']],
        ])->assertSessionHasErrors('invoice_id');
    }

    public function test_user_cannot_create_note_for_another_company_invoice(): void
    {
        $userA = $this->facturador();
        $userB = $this->facturador();
        $invoiceB = $this->issuedInvoiceFor($userB);

        $this->actingAs($userA)->get(route('credit-notes.create', ['invoice_id' => $invoiceB->id]))
            ->assertNotFound();
    }

    public function test_credit_note_uses_own_nc_numbering_sequence(): void
    {
        $user = $this->facturador();
        $resolution = $this->createCreditNoteResolution($user, 7);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '1');

        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));

        $this->assertSame('NC-000007', $creditNote->refresh()->number);
        $this->assertStringStartsWith('SIM-NC-', $creditNote->simulated_cufe);
        $this->assertSame(8, $resolution->refresh()->current_consecutive);
    }

    public function test_simulated_rejection_keeps_note_unissued(): void
    {
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '1');
        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));

        app(IssueCreditNoteService::class)->issue($creditNote->refresh(), 'rechazada');

        $this->assertSame(CreditNoteStatus::SimulatedRejected, $creditNote->refresh()->status);
        $this->assertNull($creditNote->number);
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'credit_note_simulated_rejected',
        ]);
    }

    public function test_credit_note_pdf_is_generated_for_issued_note(): void
    {
        Storage::fake('local');
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '1');
        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));

        $response = $this->actingAs($user)->get(route('credit-notes.pdf.show', $creditNote->refresh()));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $creditNote->refresh();
        Storage::disk('local')->assertExists($creditNote->pdf_path);
        $this->assertNotNull($creditNote->pdf_hash);
    }

    public function test_traceability_records_credit_note_events(): void
    {
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '1');
        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));

        $types = InvoiceEvent::where('invoice_id', $invoice->id)->pluck('type')->all();

        $this->assertContains('credit_note_draft_created', $types);
        $this->assertContains('credit_note_validated', $types);
        $this->assertContains('credit_note_issuance_requested', $types);
        $this->assertContains('credit_note_simulated_approved', $types);
        $this->assertContains('credit_note_issued', $types);
    }

    public function test_permissions_for_credit_notes(): void
    {
        $company = Company::factory()->create();
        $facturador = $this->facturador($company);
        $contador = $this->contador($company);
        $auditor = $this->auditor($company);
        $invoice = $this->issuedInvoiceFor($facturador);

        $this->actingAs($contador)->get(route('credit-notes.index'))->assertOk();
        $this->actingAs($auditor)->get(route('credit-notes.index'))->assertOk();
        $this->actingAs($contador)->get(route('credit-notes.create', ['invoice_id' => $invoice->id]))->assertForbidden();
        $this->actingAs($facturador)->get(route('credit-notes.create', ['invoice_id' => $invoice->id]))->assertOk();
    }

    public function test_double_total_annulment_is_blocked(): void
    {
        $user = $this->facturador();
        $this->createCreditNoteResolution($user);
        $invoice = $this->issuedInvoiceFor($user);
        $creditNote = $this->createCreditNote($user, $invoice, '2', 'PROTO_TOTAL_VOID');
        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));

        $this->assertSame(InvoiceStatus::Voided, $invoice->refresh()->status);

        $this->actingAs($user)->get(route('credit-notes.create', ['invoice_id' => $invoice->id]))
            ->assertOk()
            ->assertDontSee('Cantidad a acreditar');

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_TOTAL_VOID',
            'reason_text' => 'Intento duplicado',
            'items' => [$item->id => ['quantity' => '2']],
        ])->assertSessionHasErrors('invoice_id');
    }
}
