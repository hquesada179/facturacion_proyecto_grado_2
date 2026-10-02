<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_only_sees_customers_from_their_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $customerA = Customer::forceCreate([
            'company_id' => $companyA->id,
            'identification_type' => 'CC',
            'identification_number' => '1000000001',
            'name' => 'Cliente Empresa A',
        ]);

        $customerB = Customer::forceCreate([
            'company_id' => $companyB->id,
            'identification_type' => 'CC',
            'identification_number' => '2000000002',
            'name' => 'Cliente Empresa B',
        ]);

        $userA = User::factory()->for($companyA)->create();

        $this->actingAs($userA);

        $visibleCustomers = Customer::all();

        $this->assertCount(1, $visibleCustomers);
        $this->assertTrue($visibleCustomers->contains($customerA));
        $this->assertFalse($visibleCustomers->contains($customerB));

        $this->assertNull(Customer::find($customerB->id));
    }

    public function test_creating_a_record_automatically_assigns_the_authenticated_users_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);

        $customer = Customer::create([
            'identification_type' => 'CC',
            'identification_number' => '3000000003',
            'name' => 'Cliente sin empresa explícita',
        ]);

        $this->assertSame($company->id, $customer->company_id);
    }

    public function test_a_user_without_a_company_sees_nothing(): void
    {
        $company = Company::factory()->create();

        Customer::forceCreate([
            'company_id' => $company->id,
            'identification_type' => 'CC',
            'identification_number' => '4000000004',
            'name' => 'Cliente huérfano',
        ]);

        $userWithoutCompany = User::factory()->create(['company_id' => null]);

        $this->actingAs($userWithoutCompany);

        $this->assertCount(0, Customer::all());
    }
}
