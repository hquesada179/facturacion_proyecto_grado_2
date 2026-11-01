<?php

namespace Tests\Feature\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3's central fix: "Precio incluye impuesto" was stored on
 * ProductService but never actually affected a single calculation —
 * InvoiceCalculator always treated unit_price as the pre-tax base. These
 * tests cover both directions (tax-exclusive and tax-inclusive pricing)
 * end to end through the real wizard, and the specific regression risks
 * that came with fixing it (false "catalog deviation" warnings, and
 * double-converting an already-stored base price on a later update).
 */
class InvoiceItemTaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(): User
    {
        return User::factory()->for(Company::factory())->facturador()->create();
    }

    private function draftInvoiceFor(User $user): Invoice
    {
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        return $invoice;
    }

    public function test_tax_exclusive_product_price_is_billed_on_top_of_the_base(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00', 'tax_included' => false]);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '100000',
            'tax_ids' => [$tax->id],
        ]);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('100000.00', (string) $item->unit_price);
        $this->assertSame('100000.00', (string) $item->taxable_base);
        $this->assertSame('19000.00', (string) $item->tax_total);
        $this->assertSame('119000.00', (string) $item->line_total);

        $invoice->refresh();
        $this->assertSame('119000.00', (string) $invoice->total);
    }

    public function test_tax_inclusive_product_price_is_converted_to_its_pre_tax_base(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '119000.00', 'tax_included' => true]);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '119000',
            'tax_ids' => [$tax->id],
        ]);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        // base = 119000 / 1.19 = 100000 exactly.
        $this->assertSame('100000.00', (string) $item->unit_price);
        $this->assertSame('100000.00', (string) $item->taxable_base);
        $this->assertSame('19000.00', (string) $item->tax_total);
        $this->assertSame('119000.00', (string) $item->line_total);

        $invoice->refresh();
        $this->assertSame('119000.00', (string) $invoice->total);
    }

    public function test_tax_inclusive_conversion_scales_with_quantity(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '119000.00', 'tax_included' => true]);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '119000',
            'tax_ids' => [$tax->id],
        ]);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('100000.00', (string) $item->unit_price);
        $this->assertSame('200000.00', (string) $item->taxable_base);
        $this->assertSame('38000.00', (string) $item->tax_total);
        $this->assertSame('238000.00', (string) $item->line_total);
    }

    /**
     * Regression guard: updateItem() only ever exposes "quantity" for
     * editing — unit_price is passed through unchanged, already holding
     * the base from creation. It must never be re-run through the
     * gross-to-base conversion, or a tax-included line's price would be
     * silently corrupted every time its quantity changes.
     */
    public function test_updating_quantity_does_not_reconvert_the_already_stored_base_price(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '119000.00', 'tax_included' => true]);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '119000',
            'tax_ids' => [$tax->id],
        ]);
        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('100000.00', (string) $item->unit_price);

        $this->actingAs($user)->put(route('invoices.draft.items.update', [$invoice, $item]), [
            'product_service_id' => $product->id,
            'description' => $item->description,
            'unit' => $item->unit,
            'quantity' => '3',
            'unit_price' => (string) $item->unit_price,
            'discount_percent' => '0',
            'tax_ids' => [$tax->id],
        ]);

        $item->refresh();
        $this->assertSame('100000.00', (string) $item->unit_price, 'unit_price must stay the base, not be divided by 1.19 again.');
        $this->assertSame('300000.00', (string) $item->taxable_base);
        $this->assertSame('57000.00', (string) $item->tax_total);
        $this->assertSame('357000.00', (string) $item->line_total);
    }

    public function test_custom_line_without_a_product_is_never_treated_as_tax_inclusive(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'description' => 'Línea personalizada',
            'unit' => 'unidad',
            'quantity' => '1',
            'unit_price' => '100000',
            'tax_ids' => [$tax->id],
        ]);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('100000.00', (string) $item->unit_price);
        $this->assertSame('119000.00', (string) $item->line_total);
    }

    /**
     * Regression guard: before the fix, comparing a tax-included
     * product's raw catalog price against the line's (now correctly
     * converted) base would always look like a >30% "deviation",
     * because the catalog price still includes the tax amount.
     */
    public function test_catalog_deviation_warning_does_not_false_positive_for_tax_included_products(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $customer = \App\Models\Customer::factory()->for($user->company)->create(['status' => 'active']);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '119000.00', 'tax_included' => true]);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '119000',
            'tax_ids' => [$tax->id],
        ]);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertDontSee('PRO-PRICE-003');
        $response->assertDontSee('Desviación frente al catálogo');
    }

    public function test_quick_created_tax_included_product_is_billed_correctly_once_selected(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        $this->actingAs($user)->post(route('invoices.draft.items.quick-create-product', $invoice), [
            'sku' => 'SRV-INCL',
            'name' => 'Servicio con IVA incluido',
            'type' => 'service',
            'unit' => 'servicio',
            'price' => '119000',
            'tax_included' => '1',
            'status' => 'active',
            'taxes' => [$tax->id],
        ]);

        $product = ProductService::where('sku', 'SRV-INCL')->firstOrFail();
        $this->assertTrue($product->tax_included);

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '119000',
            'tax_ids' => [$tax->id],
        ]);

        $item = InvoiceItem::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('100000.00', (string) $item->unit_price);
        $this->assertSame('119000.00', (string) $item->line_total);
    }
}
