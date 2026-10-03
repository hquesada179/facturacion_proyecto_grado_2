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
        $taxA = Tax::factory()->create(['company_id' => null]);
        $taxB = Tax::factory()->create(['company_id' => null]);

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

    public function test_contador_and_auditor_have_read_only_access(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        ProductService::factory()->for($contador->company)->create();

        $this->actingAs($contador)->get(route('products.index'))->assertOk();
        $this->actingAs($contador)->get(route('products.create'))->assertForbidden();
        $this->actingAs($contador)->post(route('products.store'), $this->validPayload())->assertForbidden();
    }
}
