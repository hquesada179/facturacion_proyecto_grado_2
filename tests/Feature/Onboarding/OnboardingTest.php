<?php

namespace Tests\Feature\Onboarding;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function adminFor(Company $company): User
    {
        return User::factory()->for($company)->create(['role' => UserRole::Administrador]);
    }

    public function test_onboarding_requires_login(): void
    {
        $this->get(route('onboarding.show'))->assertRedirect(route('login'));
    }

    public function test_show_renders_welcome_before_starting(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);

        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Configuremos Fiscora para tu empresa');
    }

    public function test_start_marks_company_started_and_advances_to_company_step(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);

        $this->actingAs($user)->post(route('onboarding.start'))
            ->assertRedirect(route('onboarding.show'));

        $company->refresh();
        $this->assertNotNull($company->onboarding_started_at);
        $this->assertSame('company', $company->onboarding_step);
    }

    public function test_company_step_saves_real_company_data_and_advances_to_billing(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);

        $response = $this->actingAs($user)->put(route('onboarding.company.update'), [
            'name' => 'Comercial Andina',
            'legal_name' => 'Comercial Andina SAS',
            'person_type' => 'juridica',
            'nit' => '900123456',
            'email' => 'contacto@andina.com',
            'phone' => '6015550100',
            'address' => 'Calle 10 # 5-23',
            'city' => 'Bogotá',
            'department' => 'Bogotá D.C.',
            'country' => 'Colombia',
        ]);

        $response->assertRedirect(route('onboarding.show'));

        $company->refresh();
        $this->assertSame('Comercial Andina SAS', $company->legal_name);
        $this->assertSame('900123456', $company->nit);
        $this->assertNotNull($company->nit_dv);
        $this->assertSame('billing', $company->onboarding_step);

        // Same Company row Configuración reads — not a parallel structure.
        $this->actingAs($user)->get(route('settings.company'))
            ->assertOk()
            ->assertSee('Comercial Andina SAS');
    }

    public function test_billing_step_saves_currency_and_main_tax(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);
        app(OnboardingService::class)->advanceStep($company, 'billing');

        $response = $this->actingAs($user)->put(route('onboarding.billing.update'), [
            'currency' => 'USD',
            'tax_regime' => 'Régimen común',
        ]);

        $response->assertRedirect(route('onboarding.show'));

        $company->refresh();
        $this->assertSame('USD', $company->currency);
        $this->assertSame('data', $company->onboarding_step);
    }

    public function test_demo_customer_is_created_once_and_associated_to_the_company(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);

        $this->actingAs($user)->post(route('onboarding.customer.demo'))
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'identification_number' => OnboardingService::DEMO_CUSTOMER_IDENTIFICATION,
        ]);
        $this->assertSame(1, Customer::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_demo_customer_is_idempotent_on_repeated_visits(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);

        $this->actingAs($user)->post(route('onboarding.customer.demo'));
        $this->actingAs($user)->post(route('onboarding.customer.demo'));
        $this->actingAs($user)->post(route('onboarding.customer.demo'));

        $this->assertSame(1, Customer::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_demo_product_is_idempotent_and_associated_to_the_company(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);

        $this->actingAs($user)->post(route('onboarding.product.demo'));
        $this->actingAs($user)->post(route('onboarding.product.demo'));

        $this->assertDatabaseHas('product_services', [
            'company_id' => $company->id,
            'sku' => OnboardingService::DEMO_PRODUCT_SKU,
        ]);
        $this->assertSame(1, ProductService::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_own_customer_is_created_through_the_real_customer_request_and_associated_to_company(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);

        $response = $this->actingAs($user)->post(route('onboarding.customer.store'), [
            'person_type' => 'natural',
            'identification_type' => 'NIT',
            'identification_number' => '111222333',
            'name' => 'Cliente Propio SAS',
            'email' => 'cliente@propio.com',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'name' => 'Cliente Propio SAS',
        ]);
    }

    public function test_own_product_is_created_through_the_real_product_request_and_associated_to_company(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);

        $response = $this->actingAs($user)->post(route('onboarding.product.store'), [
            'sku' => 'PROD-OWN-1',
            'name' => 'Producto propio',
            'type' => 'service',
            'unit' => 'unidad',
            'price' => 1000,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_services', [
            'company_id' => $company->id,
            'sku' => 'PROD-OWN-1',
        ]);
    }

    public function test_continue_from_data_advances_to_done_step(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);
        app(OnboardingService::class)->advanceStep($company, 'data');

        $this->actingAs($user)->post(route('onboarding.data.continue'))
            ->assertRedirect(route('onboarding.show'));

        $company->refresh();
        $this->assertSame('done', $company->onboarding_step);
    }

    public function test_completing_onboarding_sets_completed_at_and_redirects_to_dashboard(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);
        app(OnboardingService::class)->advanceStep($company, 'done');

        $response = $this->actingAs($user)->post(route('onboarding.complete'));
        $response->assertRedirect(route('dashboard'));

        $company->refresh();
        $this->assertNotNull($company->onboarding_completed_at);
    }

    public function test_completing_onboarding_with_tour_redirect_includes_start_tour_query(): void
    {
        $company = Company::factory()->create();
        $user = $this->adminFor($company);
        app(OnboardingService::class)->start($company);
        app(OnboardingService::class)->advanceStep($company, 'done');

        $response = $this->actingAs($user)->post(route('onboarding.complete'), ['redirect' => 'tour']);

        $response->assertRedirect(route('dashboard', ['start_tour' => 1]));
    }

    public function test_dashboard_still_requires_authentication_and_renders_the_checklist(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $company = Company::factory()->create();
        $user = $this->adminFor($company);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Primeros pasos')
            ->assertSee('Configurar empresa');
    }

    public function test_checklist_reflects_real_data_and_increases_as_data_is_created(): void
    {
        $company = Company::factory()->create([
            'legal_name' => 'Empresa Real SAS',
            'nit' => '900999888',
        ]);
        $user = $this->adminFor($company);
        $this->actingAs($user);

        $checklist = app(OnboardingService::class)->checklistFor($user);
        $this->assertSame(1, $checklist['doneCount']); // only "company" is done
        $this->assertTrue($checklist['items'][0]['done']);
        $this->assertFalse($checklist['items'][1]['done']);

        Customer::factory()->for($company)->create();
        $checklist = app(OnboardingService::class)->checklistFor($user->refresh());
        $this->assertSame(2, $checklist['doneCount']);

        ProductService::factory()->for($company)->create();
        $checklist = app(OnboardingService::class)->checklistFor($user->refresh());
        $this->assertSame(3, $checklist['doneCount']);

        // actingAs() is already active, so BelongsToCompany's creating()
        // hook assigns company_id from the authenticated user exactly
        // like every other invoice in the app gets it — never set here
        // directly, matching the model's own guarded-fields contract.
        $invoice = Invoice::create(['user_id' => $user->id]);
        $invoice->forceFill(['status' => InvoiceStatus::Issued])->save();
        $checklist = app(OnboardingService::class)->checklistFor($user->refresh());
        $this->assertSame(4, $checklist['doneCount']);

        InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'type' => 'status_change',
            'description' => 'Factura creada en borrador',
        ]);
        $checklist = app(OnboardingService::class)->checklistFor($user->refresh());
        $this->assertSame(5, $checklist['doneCount']);
        $this->assertSame(100, $checklist['percent']);
    }

    public function test_onboarding_progress_and_data_never_cross_companies(): void
    {
        $companyA = Company::factory()->create();
        $userA = $this->adminFor($companyA);

        $companyB = Company::factory()->create();
        $userB = $this->adminFor($companyB);

        app(OnboardingService::class)->start($companyA);
        $this->actingAs($userA)->post(route('onboarding.customer.demo'));

        // Company B never started onboarding and has no customers.
        $this->assertNull($companyB->refresh()->onboarding_started_at);
        $this->assertSame(0, Customer::withoutGlobalScopes()->where('company_id', $companyB->id)->count());

        // User B updating "their" company step never touches company A.
        $this->actingAs($userB)->post(route('onboarding.start'));
        $this->actingAs($userB)->put(route('onboarding.company.update'), [
            'name' => 'B',
            'legal_name' => 'Empresa B SAS',
            'person_type' => 'juridica',
            'nit' => '800111222',
            'email' => 'b@empresa.com',
            'address' => 'Dir B',
            'city' => 'Cali',
            'country' => 'Colombia',
        ]);

        $this->assertNotSame('Empresa B SAS', $companyA->refresh()->legal_name);
        $this->assertSame('Empresa B SAS', $companyB->refresh()->legal_name);
    }
}
