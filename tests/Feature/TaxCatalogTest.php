<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductService;
use App\Models\Tax;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_expected_global_tax_catalog(): void
    {
        $this->seed(TaxSeeder::class);

        $codes = Tax::query()->pluck('code')->sort()->values()->all();

        $this->assertSame(['EXCLUIDO', 'EXENTO', 'INC8', 'IVA0', 'IVA19', 'IVA5'], $codes);
        $this->assertTrue(Tax::where('code', 'IVA19')->first()->is_active);
        $this->assertNull(Tax::where('code', 'IVA19')->first()->company_id);
        $this->assertEquals(19, Tax::where('code', 'IVA19')->first()->rate);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TaxSeeder::class);
        $this->seed(TaxSeeder::class);

        $this->assertSame(6, Tax::count());
    }

    public function test_currently_valid_scope_excludes_inactive_and_out_of_range_taxes(): void
    {
        Tax::factory()->create(['code' => 'ACTIVE-NOW']);
        Tax::factory()->inactive()->create(['code' => 'INACTIVE-NOW']);
        Tax::factory()->create([
            'code' => 'EXPIRED',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->subMonth(),
        ]);
        Tax::factory()->create([
            'code' => 'NOT-YET-VALID',
            'valid_from' => now()->addMonth(),
        ]);

        $validCodes = Tax::query()->currentlyValid()->pluck('code')->sort()->values()->all();

        $this->assertSame(['ACTIVE-NOW'], $validCodes);
    }

    public function test_a_product_can_have_multiple_taxes_via_the_pivot_table(): void
    {
        $company = Company::factory()->create();
        $product = ProductService::factory()->for($company)->create();
        $taxA = Tax::factory()->create();
        $taxB = Tax::factory()->create();

        $product->taxes()->attach([$taxA->id, $taxB->id]);

        $this->assertCount(2, $product->taxes()->get());
        $this->assertCount(1, $taxA->productServices()->get());
    }

    public function test_available_for_scope_returns_global_and_company_specific_taxes(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Tax::factory()->create(['code' => 'GLOBAL', 'company_id' => null]);
        Tax::factory()->create(['code' => 'COMPANY-A', 'company_id' => $companyA->id]);
        Tax::factory()->create(['code' => 'COMPANY-B', 'company_id' => $companyB->id]);

        $available = Tax::query()->availableFor($companyA->id)->pluck('code')->sort()->values()->all();

        $this->assertSame(['COMPANY-A', 'GLOBAL'], $available);
    }
}
