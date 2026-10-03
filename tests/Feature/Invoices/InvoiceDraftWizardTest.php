<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDraftWizardTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(): User
    {
        return User::factory()->for(Company::factory())->facturador()->create();
    }

    /**
     * company_id is intentionally guarded on Invoice (never mass-assigned
     * from request input) and is normally set by BelongsToCompany's
     * creating hook from Auth::user() — which isn't set yet at this point
     * in most of these tests (actingAs() runs after). Set it directly.
     */
    private function draftInvoiceFor(User $user): Invoice
    {
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        return $invoice;
    }

    public function test_creating_a_draft_creates_a_real_invoice_in_draft_status(): void
    {
        $user = $this->facturador();

        $response = $this->actingAs($user)->get(route('invoices.create.customer'));

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($user->company_id, $invoice->company_id);
        $this->assertSame($user->id, $invoice->user_id);
        $this->assertNull($invoice->number);

        $response->assertRedirect(route('invoices.draft.customer', $invoice));
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'draft_created']);
    }

    public function test_selecting_a_customer_persists_and_still_appears_when_revisiting_step_one(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['name' => 'Comercializadora Andina SAS']);

        $this->actingAs($user)
            ->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id])
            ->assertRedirect(route('invoices.draft.customer', $invoice));

        $invoice->refresh();
        $this->assertSame($customer->id, $invoice->customer_id);

        $this->actingAs($user)
            ->get(route('invoices.draft.customer', $invoice))
            ->assertOk()
            ->assertSee('Comercializadora Andina SAS');
    }

    public function test_adding_a_product_persists_a_real_invoice_item_and_recalculates_totals(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $product = ProductService::factory()->for($user->company)->create(['name' => 'Servicio de soporte', 'price' => '1000.00']);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '1000',
            'discount_percent' => '0',
            'tax_ids' => [$tax->id],
        ])->assertRedirect(route('invoices.draft.items', $invoice));

        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'description' => 'Servicio de soporte']);

        $invoice->refresh();
        $this->assertSame('1000.00', (string) $invoice->subtotal);
        $this->assertSame('190.00', (string) $invoice->tax_total);
        $this->assertSame('1190.00', (string) $invoice->total);
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'item_added']);
    }

    public function test_updating_quantity_to_four_is_reflected_in_the_table_and_totals(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $product = ProductService::factory()->for($user->company)->create(['price' => '1000.00']);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '1000',
        ]);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
        $this->assertSame('1.00', (string) $item->quantity);

        $this->actingAs($user)->put(route('invoices.draft.items.update', [$invoice, $item]), [
            'product_service_id' => $product->id,
            'description' => $item->description,
            'unit' => $item->unit,
            'quantity' => '4',
            'unit_price' => '1000',
            'discount_percent' => '0',
        ])->assertRedirect(route('invoices.draft.items', $invoice));

        $item->refresh();
        $invoice->refresh();

        // The bug this phase fixes: quantity=4 must be the value stored AND
        // the value the table/totals are computed from — never stale data.
        $this->assertSame('4.00', (string) $item->quantity);
        $this->assertSame('4000.00', (string) $item->line_total);
        $this->assertSame('4000.00', (string) $invoice->subtotal);
        $this->assertSame('4000.00', (string) $invoice->total);

        $this->actingAs($user)->get(route('invoices.draft.items', $invoice))
            ->assertOk()
            ->assertSee('value="4.00"', false)
            ->assertSee('4.000,00', false);

        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'item_updated']);
    }

    public function test_removing_an_item_recalculates_totals(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $productA = ProductService::factory()->for($user->company)->create(['price' => '1000.00']);
        $productB = ProductService::factory()->for($user->company)->create(['price' => '500.00']);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $productA->id, 'quantity' => '1', 'unit_price' => '1000']);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $productB->id, 'quantity' => '1', 'unit_price' => '500']);

        $invoice->refresh();
        $this->assertSame('1500.00', (string) $invoice->subtotal);

        $itemToRemove = InvoiceItem::where('invoice_id', $invoice->id)->where('description', $productB->name)->first();

        $this->actingAs($user)->delete(route('invoices.draft.items.destroy', [$invoice, $itemToRemove]))
            ->assertRedirect(route('invoices.draft.items', $invoice));

        $this->assertDatabaseMissing('invoice_items', ['id' => $itemToRemove->id]);

        $invoice->refresh();
        $this->assertSame('1000.00', (string) $invoice->subtotal);
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'item_removed']);
    }

    public function test_summary_shows_real_totals_not_prototype_data(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $product = ProductService::factory()->for($user->company)->create(['price' => '1000.00']);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '2', 'unit_price' => '1000']);

        $response = $this->actingAs($user)->get(route('invoices.draft.summary', $invoice));

        $response->assertOk();
        $response->assertSee('2.000,00', false);
        // The old static prototype total must never appear on a real draft.
        $response->assertDontSee('1.428.000');
    }

    public function test_a_user_cannot_access_another_companys_draft(): void
    {
        $companyB = Company::factory()->create();
        $otherInvoice = new Invoice;
        $otherInvoice->company_id = $companyB->id;
        $otherInvoice->save();

        $user = $this->facturador();

        $this->actingAs($user)->get(route('invoices.draft.customer', $otherInvoice))->assertNotFound();
    }

    public function test_editing_is_blocked_once_the_invoice_is_issued(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $invoice->status = InvoiceStatus::Issued;
        $invoice->save();

        $this->actingAs($user)
            ->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => Customer::factory()->for($user->company)->create()->id])
            ->assertForbidden();

        $product = ProductService::factory()->for($user->company)->create();
        $this->actingAs($user)
            ->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100'])
            ->assertForbidden();
    }

    public function test_contador_cannot_mutate_a_draft_but_can_view_it(): void
    {
        $company = Company::factory()->create();
        $contador = User::factory()->for($company)->contador()->create();
        $invoice = $this->draftInvoiceFor($contador);

        $this->actingAs($contador)->get(route('invoices.draft.customer', $invoice))->assertOk();
        $this->actingAs($contador)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => 1])->assertForbidden();
    }
}
