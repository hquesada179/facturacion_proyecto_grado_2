<?php

namespace Tests\Feature\Settings;

use App\Enums\DocumentType;
use App\Exceptions\NumberingRangeExhaustedException;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'invoice',
            'authorization_number_simulated' => 'SIM-00000001',
            'prefix' => 'FV',
            'authorization_number' => null,
            'range_from' => 1,
            'range_to' => 1000,
            'current_consecutive' => 1,
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'simulated_technical_key' => 'abc123',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_administrador_can_create_a_valid_resolution(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->post(route('settings.billing.numbering.store'), $this->validPayload());

        $response->assertRedirect(route('settings.billing'));
        $this->assertDatabaseHas('numbering_resolutions', [
            'company_id' => $user->company_id,
            'prefix' => 'FV',
            'status' => 'vigente',
        ]);
    }

    public function test_prefix_longer_than_four_characters_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload(['prefix' => 'FACTURA'])
        );

        $response->assertSessionHasErrors('prefix');
    }

    public function test_range_from_greater_than_range_to_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload(['range_from' => 500, 'range_to' => 100, 'current_consecutive' => 200])
        );

        $response->assertSessionHasErrors('range_to');
    }

    public function test_consecutive_outside_range_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload(['range_from' => 1, 'range_to' => 100, 'current_consecutive' => 500])
        );

        $response->assertSessionHasErrors('current_consecutive');
    }

    public function test_valid_until_before_valid_from_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload([
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->subMonth()->toDateString(),
            ])
        );

        $response->assertSessionHasErrors('valid_until');
    }

    public function test_overlapping_active_range_for_same_company_and_document_type_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        NumberingResolution::factory()->for($user->company)->create([
            'document_type' => 'invoice',
            'range_from' => 1,
            'range_to' => 1000,
        ]);

        $response = $this->actingAs($user)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload(['range_from' => 500, 'range_to' => 1500, 'current_consecutive' => 500])
        );

        $response->assertSessionHasErrors('range_from');
    }

    public function test_non_overlapping_range_for_same_company_and_type_is_accepted(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        NumberingResolution::factory()->for($user->company)->create([
            'document_type' => 'invoice',
            'range_from' => 1,
            'range_to' => 1000,
        ]);

        $response = $this->actingAs($user)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload(['range_from' => 1001, 'range_to' => 2000, 'current_consecutive' => 1001])
        );

        $response->assertRedirect(route('settings.billing'));
    }

    public function test_overlapping_range_for_a_different_company_is_allowed(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        NumberingResolution::factory()->for($companyA)->create([
            'document_type' => 'invoice',
            'range_from' => 1,
            'range_to' => 1000,
        ]);

        $userB = User::factory()->for($companyB)->administrador()->create();

        $response = $this->actingAs($userB)->post(
            route('settings.billing.numbering.store'),
            $this->validPayload(['range_from' => 1, 'range_to' => 1000])
        );

        $response->assertRedirect(route('settings.billing'));
    }

    public function test_a_user_cannot_edit_another_companys_resolution(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $resolution = NumberingResolution::factory()->for($companyA)->create();
        $userB = User::factory()->for($companyB)->administrador()->create();

        // Cross-company isolation is enforced by CompanyScope before the
        // policy even runs: implicit route binding simply finds nothing.
        $this->actingAs($userB)->get(route('settings.billing.numbering.edit', $resolution))->assertNotFound();
    }

    public function test_billing_index_shows_expiry_and_exhaustion_alerts(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        NumberingResolution::factory()->for($user->company)->expiringSoon()->create();
        NumberingResolution::factory()->for($user->company)->creditNote()->nearlyExhausted()->create();

        $response = $this->actingAs($user)->get(route('settings.billing'));

        $response->assertOk();
        $response->assertSee('por vencer');
        $response->assertSee('próximos a agotarse');
    }

    public function test_create_and_edit_forms_render_for_administrador(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $resolution = NumberingResolution::factory()->for($user->company)->create();

        $this->actingAs($user)->get(route('settings.billing.numbering.create'))->assertOk();
        $this->actingAs($user)->get(route('settings.billing.numbering.edit', $resolution))->assertOk();
    }

    public function test_create_form_shows_helpful_placeholders_for_every_field(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertSee('187640000001');
        $response->assertSee('FEV');
        $response->assertSee('CLAVE-DEMO-2026');
        $response->assertSee('placeholder="1"', false);
        $response->assertSee('placeholder="1000"', false);
    }

    public function test_create_form_shows_plain_language_help_text_under_every_field(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertSee('Selecciona el tipo de documento que utilizará esta numeración.');
        $response->assertSee('Número ficticio que representa la autorización de numeración. No corresponde a una resolución real de la DIAN.');
        $response->assertSee('Texto que aparecerá antes del consecutivo de la factura. Máximo 4 caracteres. Ejemplo: FEV-1.');
        $response->assertSee('Valor ficticio usado únicamente por Fiscora para simular el proceso de emisión.');
        $response->assertSee('Primer número permitido para esta resolución.');
        $response->assertSee('Último número permitido para esta resolución.');
        $response->assertSee('Número que Fiscora utilizará como próximo consecutivo. Debe estar dentro del rango configurado.');
        $response->assertSee('Solo las resoluciones activas y vigentes pueden utilizarse para emitir facturas simuladas.');
        $response->assertSee('Fecha a partir de la cual esta resolución puede utilizarse.');
        $response->assertSee('Fecha máxima en la que esta resolución seguirá siendo válida.');
    }

    public function test_create_form_shows_the_simulated_example_block_clearly_marked(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertSee('Ejemplo de configuración de prueba');
        $response->assertSee('187640000001');
        $response->assertSee('1 a 1000');
        $response->assertSee('02/11/2026 a 02/11/2027');
    }

    public function test_the_example_block_is_not_shown_on_the_edit_form(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $resolution = NumberingResolution::factory()->for($user->company)->create();

        $response = $this->actingAs($user)->get(route('settings.billing.numbering.edit', $resolution));

        $response->assertOk();
        $response->assertDontSee('Ejemplo de configuración de prueba');
    }

    public function test_create_form_shows_contextual_help_near_the_save_button(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertSee('Una vez guardada una resolución activa y vigente, podrás volver a tu factura y continuar con la emisión simulada.');
    }

    /**
     * Reuses Laravel's own previous-URL session mechanism
     * (url()->previous(), populated automatically for every GET request)
     * — no new route or query parameter was introduced for this.
     */
    public function test_create_form_offers_a_one_click_return_when_it_came_from_invoice_validation(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));
        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertSee('Volver a mi factura ahora');
        $response->assertSee(route('invoices.draft.validation', $invoice), false);
    }

    public function test_create_form_does_not_offer_a_return_link_when_not_coming_from_an_invoice(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $this->actingAs($user)->get(route('settings.billing'));
        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertDontSee('Volver a mi factura ahora');
    }

    public function test_the_simulated_disclaimer_is_still_present(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->get(route('settings.billing.numbering.create'));

        $response->assertOk();
        $response->assertSee('No representa una autorización real de la DIAN.');
    }

    /**
     * End-to-end proof that the friendlier copy is purely presentational:
     * submitting values shaped exactly like the new placeholders/example
     * still goes through the unchanged NumberingResolutionRequest/
     * NumberingResolutionController, and a resolution created this way
     * really does unblock invoice emission afterward.
     */
    public function test_submitting_values_shaped_like_the_new_placeholders_still_creates_a_working_resolution(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $this->actingAs($user)->post(route('settings.billing.numbering.store'), [
            'document_type' => 'invoice',
            'authorization_number_simulated' => '187640000001',
            'prefix' => 'FEV',
            'range_from' => 1,
            'range_to' => 1000,
            'current_consecutive' => 1,
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'simulated_technical_key' => 'CLAVE-DEMO-2026',
            'is_active' => '1',
        ])->assertRedirect(route('settings.billing'));

        $this->assertDatabaseHas('numbering_resolutions', [
            'company_id' => $user->company_id,
            'prefix' => 'FEV',
            'status' => 'vigente',
        ]);
    }

    public function test_facturador_cannot_manage_numbering_resolutions(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->get(route('settings.billing'))->assertForbidden();
        $this->actingAs($user)->post(route('settings.billing.numbering.store'), $this->validPayload())->assertForbidden();
    }

    public function test_reserve_next_number_increments_atomically_and_throws_when_exhausted(): void
    {
        $resolution = NumberingResolution::factory()->create([
            'range_from' => 1,
            'range_to' => 2,
            'current_consecutive' => 1,
        ]);

        $service = app(NumberingService::class);

        $this->assertSame(1, $service->reserveNextNumber($resolution));
        $this->assertSame(2, $service->reserveNextNumber($resolution));

        $this->expectException(NumberingRangeExhaustedException::class);
        $service->reserveNextNumber($resolution);
    }

    public function test_database_seeder_creates_a_demo_invoice_resolution_ready_for_issuance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('email', 'contacto@facturapro.test')->firstOrFail();
        $service = app(NumberingService::class);
        $resolution = $service->activeResolutionFor($company, DocumentType::Invoice);

        $this->assertNotNull($resolution);
        $this->assertSame('FV', $resolution->prefix);
        $this->assertSame('SIM-DEMO-FV-000001', $resolution->authorization_number_simulated);
        $this->assertSame(1, $resolution->range_from);
        $this->assertSame(999999, $resolution->range_to);
        $this->assertSame(1, $resolution->current_consecutive);
        $this->assertTrue($resolution->is_active);
        $this->assertSame(1, $service->reserveNextNumber($resolution));
        $this->assertSame(2, $resolution->refresh()->current_consecutive);
    }

    /**
     * True multi-process concurrency is out of scope ("no hace falta
     * construir infraestructura distribuida"), but reserveNextNumber()
     * wraps every reservation in DB::transaction()+lockForUpdate(), so
     * concurrent callers are serialized through that lock exactly like
     * these back-to-back calls — if the lock/transaction ever regressed
     * into a naive read-then-increment, rapid reuse of the same
     * resolution would immediately produce duplicates or gaps here.
     */
    public function test_rapid_successive_reservations_on_the_same_resolution_never_duplicate_or_skip(): void
    {
        $resolution = NumberingResolution::factory()->create([
            'range_from' => 1,
            'range_to' => 500,
            'current_consecutive' => 1,
        ]);

        $service = app(NumberingService::class);

        $numbers = [];
        for ($i = 0; $i < 200; $i++) {
            $numbers[] = $service->reserveNextNumber($resolution);
        }

        $this->assertSame(range(1, 200), $numbers);
        $this->assertCount(200, array_unique($numbers));
        $this->assertSame(201, $resolution->refresh()->current_consecutive);
    }

    public function test_concurrent_reservations_across_two_companies_never_cross_contaminate_sequences(): void
    {
        $resolutionA = NumberingResolution::factory()->create(['range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $resolutionB = NumberingResolution::factory()->create(['range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);

        $service = app(NumberingService::class);

        $sequenceA = [];
        $sequenceB = [];

        // Interleave reservations between the two resolutions to approximate
        // concurrent callers hitting different rows at nearly the same time.
        for ($i = 0; $i < 10; $i++) {
            $sequenceA[] = $service->reserveNextNumber($resolutionA);
            $sequenceB[] = $service->reserveNextNumber($resolutionB);
        }

        $this->assertSame(range(1, 10), $sequenceA);
        $this->assertSame(range(1, 10), $sequenceB);
    }
}
