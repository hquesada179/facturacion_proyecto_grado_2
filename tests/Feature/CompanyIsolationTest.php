<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
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

    public function test_a_malicious_company_id_in_the_payload_is_overridden_by_the_authenticated_users_company(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($ownCompany)->create();

        $this->actingAs($user);

        $customer = Customer::create([
            'company_id' => $otherCompany->id,
            'identification_type' => 'CC',
            'identification_number' => '5000000005',
            'name' => 'Intento de suplantar empresa',
        ]);

        $this->assertSame($ownCompany->id, $customer->company_id);
        $this->assertNotSame($otherCompany->id, $customer->company_id);
    }

    public function test_invoice_derived_and_critical_fields_cannot_be_mass_assigned(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);

        $invoice = Invoice::create([
            'company_id' => $otherCompany->id,
            'number' => 'FV-HACKED',
            'status' => 'issued',
            'subtotal' => '1.00',
            'tax_total' => '1.00',
            'total' => '999999.00',
            'simulated_cufe' => 'SIM-HACKED',
            'issued_at' => now(),
            'validation_at' => now(),
            'pdf_path' => 'invoices/hacked.pdf',
            'pdf_hash' => 'deadbeef',
        ]);

        $this->assertSame($company->id, $invoice->company_id);
        $this->assertNull($invoice->number);
        $this->assertSame('draft', $invoice->status->value);
        $this->assertNotSame('999999.00', (string) $invoice->total);
        $this->assertNull($invoice->simulated_cufe);
        $this->assertNull($invoice->issued_at);
        $this->assertNull($invoice->validation_at);
        $this->assertNull($invoice->pdf_path);
        $this->assertNull($invoice->pdf_hash);
    }

    public function test_credit_note_derived_and_critical_fields_cannot_be_mass_assigned(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);

        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $company->id;
        $invoice->save();

        $creditNote = CreditNote::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'reason' => 'PROTO_PARTIAL_RETURN',
            'company_id' => $otherCompany->id,
            'number' => 'NC-HACKED',
            'status' => 'issued',
            'subtotal' => '1.00',
            'tax_total' => '1.00',
            'total' => '999999.00',
            'simulated_cufe' => 'SIM-NC-HACKED',
            'issued_at' => now(),
        ]);

        $this->assertSame($company->id, $creditNote->company_id);
        $this->assertNull($creditNote->number);
        $this->assertSame('draft', $creditNote->status->value);
        $this->assertNotSame('999999.00', (string) $creditNote->total);
    }
}
