<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'PS-100',
            'name' => 'Servicio de prueba',
            'description' => 'Un servicio de prueba',
            'type' => 'service',
            'unit' => 'servicio',
            'price' => 150000,
            'status' => 'active',
        ], $overrides);
    }

    public function test_facturador_can_create_a_product_with_multiple_taxes(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        // Two different IVA-nature taxes together would violate the
        // Fase 3 tax-combination rule — use IVA + INC, a legitimate
        // real-world combination, to test "multiple taxes" instead.
        $taxA = Tax::factory()->create(['company_id' => null]);
        $taxB = Tax::factory()->create(['company_id' => null, 'nature' => 'INC', 'condition' => 'gravado']);

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'taxes' => [$taxA->id, $taxB->id],
        ]));

        $product = ProductService::first();
        $response->assertRedirect(route('products.show', $product));
        $this->assertCount(2, $product->taxes);
    }

    public function test_sku_must_be_unique_per_company(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        ProductService::factory()->for($user->company)->create(['sku' => 'PS-100']);

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload());

        $response->assertSessionHasErrors('sku');
    }

    public function test_same_sku_is_allowed_across_different_companies(): void
    {
        $companyA = Company::factory()->create();
        ProductService::factory()->for($companyA)->create(['sku' => 'PS-100']);

        $userB = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($userB)->post(route('products.store'), $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('product_services', ['company_id' => $userB->company_id, 'sku' => 'PS-100']);
    }

    public function test_sku_is_required(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload(['sku' => '']));

        $response->assertSessionHasErrors('sku');
    }

    public function test_product_status_transitions_are_persisted(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $product = ProductService::factory()->for($user->company)->create(['status' => ProductStatus::Active->value]);

        $this->actingAs($user)->put(route('products.update', $product), $this->validPayload([
            'sku' => $product->sku,
            'status' => 'discontinued',
        ]));

        $this->assertSame(ProductStatus::Discontinued, $product->refresh()->status);
    }

    public function test_discontinued_products_are_excluded_from_available_for_invoicing_scope(): void
    {
        $company = Company::factory()->create();
        ProductService::factory()->for($company)->discontinued()->create();
        $active = ProductService::factory()->for($company)->create(['status' => ProductStatus::Active->value]);

        $available = ProductService::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->availableForInvoicing()
            ->get();

        $this->assertCount(1, $available);
        $this->assertTrue($available->contains($active));
    }

    public function test_product_with_invoice_items_cannot_be_physically_deleted(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $product = ProductService::factory()->for($user->company)->create();
        $customer = Customer::factory()->for($user->company)->create();

        $invoice = new Invoice(['customer_id' => $customer->id]);
        $invoice->company_id = $user->company_id;
        $invoice->number = 'FV-0001';
        $invoice->save();

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_service_id' => $product->id,
            'description' => 'Item de prueba',
        ]);

        $response = $this->actingAs($user)->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('product_services', ['id' => $product->id]);
    }

    public function test_a_user_only_sees_products_from_their_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $productA = ProductService::factory()->for($companyA)->create();
        ProductService::factory()->for($companyB)->create();

        $userA = User::factory()->for($companyA)->create();

        $this->actingAs($userA)->get(route('products.show', $productA))->assertOk();

        $otherProduct = ProductService::withoutGlobalScopes()->where('company_id', $companyB->id)->first();
        $this->actingAs($userA)->get(route('products.show', $otherProduct))->assertNotFound();
    }

    public function test_create_and_edit_forms_render_for_facturador(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $product = ProductService::factory()->for($user->company)->create();

        $this->actingAs($user)->get(route('products.create'))->assertOk();
        $this->actingAs($user)->get(route('products.edit', $product))->assertOk();
    }

    public function test_can_create_a_product_with_a_category(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'category' => 'Software',
        ]))->assertRedirect();

        $this->assertDatabaseHas('product_services', ['sku' => 'PS-100', 'category' => 'Software']);
    }

    public function test_category_is_optional(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->post(route('products.store'), $this->validPayload())
            ->assertSessionDoesntHaveErrors('category');

        $this->assertDatabaseHas('product_services', ['sku' => 'PS-100', 'category' => null]);
    }

    public function test_can_edit_category_and_unit(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $product = ProductService::factory()->for($user->company)->create(['category' => null, 'unit' => 'unidad']);

        $this->actingAs($user)->put(route('products.update', $product), $this->validPayload([
            'sku' => $product->sku,
            'category' => 'Ferretería',
            'unit' => 'caja',
        ]))->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertSame('Ferretería', $product->category);
        $this->assertSame('caja', $product->unit);
    }

    /**
     * Backward compatibility: a unit value that predates the Fase 3
     * canonical list (free text) must still render and still be editable
     * without being silently dropped.
     */
    public function test_editing_a_product_with_a_legacy_unit_value_keeps_showing_it(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $product = ProductService::factory()->for($user->company)->create(['unit' => 'rollo de 50m']);

        $response = $this->actingAs($user)->get(route('products.edit', $product));

        $response->assertOk();
        $response->assertSee('rollo de 50m');
    }

    public function test_a_service_cannot_be_marked_as_inventoried_even_if_submitted(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'type' => 'service',
            'is_inventoried' => '1',
            'stock_quantity' => '10',
        ]))->assertRedirect();

        $product = ProductService::where('sku', 'PS-100')->first();
        $this->assertFalse($product->is_inventoried);
        $this->assertNull($product->stock_quantity);
    }

    public function test_a_product_can_enable_inventory_with_initial_stock(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'type' => 'product',
            'is_inventoried' => '1',
            'stock_quantity' => '25',
        ]))->assertRedirect();

        $product = ProductService::where('sku', 'PS-100')->first();
        $this->assertTrue($product->is_inventoried);
        $this->assertSame('25.00', (string) $product->stock_quantity);
    }

    public function test_inventory_requires_a_non_negative_stock_quantity(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'type' => 'product',
            'is_inventoried' => '1',
            'stock_quantity' => '-5',
        ]));

        $response->assertSessionHasErrors('stock_quantity');
        $this->assertDatabaseMissing('product_services', ['sku' => 'PS-100']);
    }

    public function test_inventory_requires_a_stock_quantity_when_enabled(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'type' => 'product',
            'is_inventoried' => '1',
            'stock_quantity' => '',
        ]));

        $response->assertSessionHasErrors('stock_quantity');
    }

    public function test_two_iva_nature_taxes_together_are_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $iva19 = Tax::factory()->create(['company_id' => null, 'code' => 'IVA19T', 'nature' => 'IVA', 'condition' => 'gravado', 'rate' => 19]);
        $iva5 = Tax::factory()->create(['company_id' => null, 'code' => 'IVA5T', 'nature' => 'IVA', 'condition' => 'gravado', 'rate' => 5]);

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'taxes' => [$iva19->id, $iva5->id],
        ]));

        $response->assertSessionHasErrors('taxes');
        $this->assertDatabaseMissing('product_services', ['sku' => 'PS-100']);
    }

    public function test_exento_together_with_iva_gravado_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $iva19 = Tax::factory()->create(['company_id' => null, 'code' => 'IVA19T', 'nature' => 'IVA', 'condition' => 'gravado', 'rate' => 19]);
        $exento = Tax::factory()->create(['company_id' => null, 'code' => 'EXENTOT', 'nature' => 'IVA', 'condition' => 'exento', 'rate' => 0]);

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'taxes' => [$iva19->id, $exento->id],
        ]));

        $response->assertSessionHasErrors('taxes');
    }

    public function test_excluido_together_with_iva_gravado_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $iva19 = Tax::factory()->create(['company_id' => null, 'code' => 'IVA19T', 'nature' => 'IVA', 'condition' => 'gravado', 'rate' => 19]);
        $excluido = Tax::factory()->create(['company_id' => null, 'code' => 'EXCLUIDOT', 'nature' => 'ninguna', 'condition' => 'excluido', 'rate' => 0]);

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'taxes' => [$iva19->id, $excluido->id],
        ]));

        $response->assertSessionHasErrors('taxes');
    }

    public function test_iva_and_inc_together_are_allowed(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $iva19 = Tax::factory()->create(['company_id' => null, 'code' => 'IVA19T', 'nature' => 'IVA', 'condition' => 'gravado', 'rate' => 19]);
        $inc8 = Tax::factory()->create(['company_id' => null, 'code' => 'INC8T', 'nature' => 'INC', 'condition' => 'gravado', 'rate' => 8]);

        $response = $this->actingAs($user)->post(route('products.store'), $this->validPayload([
            'taxes' => [$iva19->id, $inc8->id],
        ]));

        $response->assertSessionDoesntHaveErrors('taxes');
        $this->assertDatabaseHas('product_services', ['sku' => 'PS-100']);
    }

    /**
     * Regression test: the product detail page previously hardcoded an
     * empty rows array for "Uso en facturas", so a product with real
     * invoice usage still showed the empty-state message.
     */
    public function test_product_detail_shows_its_real_invoice_usage(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $product = ProductService::factory()->for($user->company)->create();
        $customer = Customer::factory()->for($user->company)->create();

        $invoice = new Invoice(['customer_id' => $customer->id]);
        $invoice->company_id = $user->company_id;
        $invoice->number = 'FV-0001';
        $invoice->save();

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_service_id' => $product->id,
            'description' => $product->name,
            'quantity' => 3,
            'unit_price' => 1000,
            'line_total' => 3000,
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('FV-0001');
        $response->assertDontSee('Este producto todavía no se ha facturado.');
    }

    /**
     * Backward compatibility: a product created before Fase 3 (no
     * category, is_inventoried=false, stock_quantity=null, via the
     * plain factory) must still render on every page the new fields
     * touch, and must still be editable without those fields being
     * required.
     */
    public function test_pre_fase_3_product_still_works_everywhere(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $product = ProductService::factory()->for($user->company)->create()->refresh();

        $this->assertNull($product->category);
        $this->assertFalse($product->is_inventoried);
        $this->assertNull($product->stock_quantity);

        $this->actingAs($user)->get(route('products.show', $product))->assertOk();
        $this->actingAs($user)->get(route('products.edit', $product))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();

        $this->actingAs($user)->put(route('products.update', $product), $this->validPayload([
            'sku' => $product->sku,
        ]))->assertRedirect(route('products.show', $product));
    }

    public function test_contador_and_auditor_have_read_only_access(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        ProductService::factory()->for($contador->company)->create();

        $this->actingAs($contador)->get(route('products.index'))->assertOk();
        $this->actingAs($contador)->get(route('products.create'))->assertForbidden();
        $this->actingAs($contador)->post(route('products.store'), $this->validPayload())->assertForbidden();
    }
}
