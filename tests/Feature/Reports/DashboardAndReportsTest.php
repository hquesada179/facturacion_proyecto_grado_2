<?php

namespace Tests\Feature\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\AssistantConversation;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Reports\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private int $invoiceSequence = 1;

    private int $creditNoteSequence = 1;

    public function test_dashboard_uses_real_metrics_and_recent_documents_for_the_company(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $otherCompanyUser = User::factory()->for(Company::factory())->administrador()->create();
        $customer = Customer::factory()->for($user->company)->create(['name' => 'Cliente Reportable']);

        $issued = $this->invoiceFor($user, [
            'customer_id' => $customer->id,
            'number' => 'FV-REAL-001',
            'status' => InvoiceStatus::Issued,
            'created_at' => now()->subMinutes(10),
            'issued_at' => now()->subMinutes(5),
            'total' => 119000,
        ]);
        $this->invoiceFor($user, ['status' => InvoiceStatus::Draft, 'number' => 'FV-DRAFT-001']);
        $this->invoiceFor($user, ['status' => InvoiceStatus::TechnicalError, 'number' => 'FV-ERR-001']);
        $this->invoiceFor($otherCompanyUser, ['status' => InvoiceStatus::Issued, 'number' => 'FV-OTHER-001']);

        $this->creditNoteFor($user, $issued, [
            'number' => 'NC-REAL-001',
            'status' => CreditNoteStatus::Issued,
            'total' => 19000,
        ]);
        $this->assistantConversationFor($user, ['status' => 'resolved']);

        $dashboard = app(DashboardMetricsService::class)->dataFor($user);
        $metrics = collect($dashboard['metrics'])->pluck('value', 'label');

        $this->assertSame('1', $metrics['Facturas emitidas']);
        $this->assertSame('1', $metrics['Pendientes y borradores']);
        $this->assertSame('1', $metrics['Errores o rechazos']);
        $this->assertSame('1', $metrics['Notas crédito emitidas']);
        $this->assertSame('5 min', $metrics['Tiempo promedio de emisión']);
        $this->assertSame('1', $metrics['Asistente resuelto']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('FV-REAL-001');
        $response->assertSee('NC-REAL-001');
        $response->assertSee('Cliente Reportable');
        $response->assertDontSee('FV-OTHER-001');
    }

    public function test_facturador_dashboard_is_limited_to_own_documents(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->facturador()->create(['name' => 'Facturador Propio']);
        $other = User::factory()->for($company)->facturador()->create(['name' => 'Facturador Otro']);

        $this->invoiceFor($owner, ['status' => InvoiceStatus::Issued, 'number' => 'FV-OWN-001']);
        $this->invoiceFor($other, ['status' => InvoiceStatus::Issued, 'number' => 'FV-OTHER-USER']);

        $dashboard = app(DashboardMetricsService::class)->dataFor($owner);
        $metrics = collect($dashboard['metrics'])->pluck('value', 'label');

        $this->assertSame('1', $metrics['Facturas emitidas']);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('FV-OWN-001');
        $response->assertDontSee('FV-OTHER-USER');
        $response->assertSee('únicamente los documentos asociados a tu usuario facturador', false);
    }

    public function test_reports_apply_date_status_customer_filters_and_include_credit_notes(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $includedCustomer = Customer::factory()->for($user->company)->create(['name' => 'Cliente Incluido']);
        $excludedCustomer = Customer::factory()->for($user->company)->create(['name' => 'Cliente Excluido']);

        $includedInvoice = $this->invoiceFor($user, [
            'customer_id' => $includedCustomer->id,
            'number' => 'FV-FILTER-001',
            'status' => InvoiceStatus::Issued,
            'created_at' => now()->subDay(),
            'issued_at' => now()->subDay()->addMinutes(6),
            'subtotal' => 100000,
            'tax_total' => 19000,
            'total' => 119000,
        ]);
        $this->invoiceItemFor($includedInvoice, ['description' => 'Servicio de consultoría', 'line_total' => 100000]);

        $this->invoiceFor($user, [
            'customer_id' => $excludedCustomer->id,
            'number' => 'FV-FILTER-002',
            'status' => InvoiceStatus::Issued,
            'created_at' => now()->subDay(),
            'issued_at' => now()->subDay()->addMinutes(8),
        ]);
        $this->invoiceFor($user, [
            'customer_id' => $includedCustomer->id,
            'number' => 'FV-OLD-001',
            'status' => InvoiceStatus::Issued,
            'created_at' => now()->subDays(60),
            'issued_at' => now()->subDays(60)->addMinutes(3),
        ]);

        $this->creditNoteFor($user, $includedInvoice, [
            'number' => 'NC-FILTER-001',
            'status' => CreditNoteStatus::Issued,
            'created_at' => now()->subDay(),
            'issued_at' => now()->subDay()->addMinutes(12),
            'total' => 19000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'date_range' => 'custom',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'issued',
            'customer_id' => $includedCustomer->id,
        ]));

        $response->assertOk();
        $response->assertSee('FV-FILTER-001');
        $response->assertSee('NC-FILTER-001');
        $response->assertSee('Servicio de consultoría');
        $response->assertSee('Cliente Incluido');
        $response->assertDontSee('FV-FILTER-002');
        $response->assertDontSee('FV-OLD-001');
        $response->assertSee('$ 100.000,00');
        $response->assertSee('$ 19.000,00');
    }

    public function test_csv_export_respects_filters_and_company_scope(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $otherCompanyUser = User::factory()->for(Company::factory())->administrador()->create();

        $this->invoiceFor($user, ['number' => 'FV-CSV-001', 'status' => InvoiceStatus::Issued]);
        $this->invoiceFor($otherCompanyUser, ['number' => 'FV-CSV-OTHER', 'status' => InvoiceStatus::Issued]);

        $response = $this->actingAs($user)->get(route('reports.export', [
            'date_range' => 'last_30_days',
            'document_type' => 'invoices',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('FV-CSV-001', $content);
        $this->assertStringNotContainsString('FV-CSV-OTHER', $content);
    }

    public function test_report_permissions_and_facturador_scope_are_respected(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->for($company)->administrador()->create();
        $auditor = User::factory()->for($company)->auditor()->create();
        $contador = User::factory()->for($company)->contador()->create();
        $facturador = User::factory()->for($company)->facturador()->create(['name' => 'Facturador Alcance']);
        $otherFacturador = User::factory()->for($company)->facturador()->create();

        $this->invoiceFor($facturador, ['number' => 'FV-SCOPE-OWN', 'status' => InvoiceStatus::Issued]);
        $this->invoiceFor($otherFacturador, ['number' => 'FV-SCOPE-OTHER', 'status' => InvoiceStatus::Issued]);

        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()->assertSee('Reporte por usuarios');
        $this->actingAs($auditor)->get(route('reports.index'))->assertOk()->assertSee('Reporte por usuarios');
        $this->actingAs($contador)->get(route('reports.index'))->assertOk()->assertSee('Tu rol no muestra desglose por usuario');

        $facturadorResponse = $this->actingAs($facturador)->get(route('reports.index'));
        $facturadorResponse->assertOk();
        $facturadorResponse->assertSee('FV-SCOPE-OWN');
        $facturadorResponse->assertDontSee('FV-SCOPE-OTHER');
        $facturadorResponse->assertSee('limita estos indicadores a documentos creados por tu usuario', false);
    }

    public function test_error_report_reads_validation_metadata_without_fake_rules(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $invoice = $this->invoiceFor($user, ['status' => InvoiceStatus::Draft, 'number' => 'FV-ERROR-001']);

        InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'type' => 'local_validation_failed',
            'description' => 'Falló validación local.',
            'metadata' => [
                'results' => [
                    'blocking_count' => 1,
                    'warning_count' => 1,
                    'results' => [
                        [
                            'codigo' => 'PRO-CUS-001',
                            'regla' => 'Cliente obligatorio',
                            'severidad' => 'bloqueo',
                            'campo' => 'customer_id',
                        ],
                    ],
                ],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('reports.errors'));

        $response->assertOk();
        $response->assertSee('PRO-CUS-001');
        $response->assertSee('Cliente obligatorio');
        $response->assertSee('customer_id');
    }

    public function test_empty_reports_show_empty_states_without_fake_numbers(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aún no hay facturas ni notas crédito reales para mostrar.');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('No hay documentos en el rango seleccionado.')
            ->assertSee('No hay documentos para el filtro aplicado.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function invoiceFor(User $user, array $overrides = []): Invoice
    {
        $customerId = $overrides['customer_id'] ?? Customer::factory()->for($user->company)->create()->id;
        $status = $overrides['status'] ?? InvoiceStatus::Issued;
        $createdAt = $overrides['created_at'] ?? now()->subDay();
        $issuedAt = $overrides['issued_at'] ?? ($status === InvoiceStatus::Draft ? null : $createdAt->copy()->addMinutes(5));

        $invoice = new Invoice;
        $invoice->company_id = $overrides['company_id'] ?? $user->company_id;
        $invoice->customer_id = $customerId;
        $invoice->user_id = $overrides['user_id'] ?? $user->id;
        $invoice->number = $overrides['number'] ?? 'FV-TEST-'.str_pad((string) $this->invoiceSequence++, 4, '0', STR_PAD_LEFT);
        $invoice->status = $status;
        $invoice->issue_date = $overrides['issue_date'] ?? $createdAt->toDateString();
        $invoice->subtotal = $overrides['subtotal'] ?? 100000;
        $invoice->tax_total = $overrides['tax_total'] ?? 19000;
        $invoice->total = $overrides['total'] ?? 119000;
        $invoice->issued_at = $issuedAt;
        $invoice->created_at = $createdAt;
        $invoice->updated_at = $overrides['updated_at'] ?? $createdAt;
        $invoice->save();

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function creditNoteFor(User $user, Invoice $invoice, array $overrides = []): CreditNote
    {
        $status = $overrides['status'] ?? CreditNoteStatus::Issued;
        $createdAt = $overrides['created_at'] ?? now()->subHours(12);
        $issuedAt = $overrides['issued_at'] ?? ($status === CreditNoteStatus::Draft ? null : $createdAt->copy()->addMinutes(3));

        $creditNote = new CreditNote;
        $creditNote->company_id = $user->company_id;
        $creditNote->invoice_id = $invoice->id;
        $creditNote->user_id = $overrides['user_id'] ?? $user->id;
        $creditNote->number = $overrides['number'] ?? 'NC-TEST-'.str_pad((string) $this->creditNoteSequence++, 4, '0', STR_PAD_LEFT);
        $creditNote->reason = $overrides['reason'] ?? 'PROTO_VALUE_CORRECTION';
        $creditNote->reason_code = $overrides['reason_code'] ?? 'PROTO_VALUE_CORRECTION';
        $creditNote->status = $status;
        $creditNote->subtotal = $overrides['subtotal'] ?? 15966.39;
        $creditNote->tax_total = $overrides['tax_total'] ?? 3033.61;
        $creditNote->total = $overrides['total'] ?? 19000;
        $creditNote->issued_at = $issuedAt;
        $creditNote->created_at = $createdAt;
        $creditNote->updated_at = $overrides['updated_at'] ?? $createdAt;
        $creditNote->save();

        CreditNoteItem::create([
            'credit_note_id' => $creditNote->id,
            'product_code' => 'SRV-001',
            'description' => 'Corrección documental',
            'original_quantity' => 1,
            'credited_quantity' => 1,
            'unit_price' => 15966.39,
            'taxable_base' => 15966.39,
            'tax_total' => 3033.61,
            'line_total' => $creditNote->total,
        ]);

        return $creditNote;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function invoiceItemFor(Invoice $invoice, array $overrides = []): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_code' => $overrides['product_code'] ?? 'SRV-001',
            'description' => $overrides['description'] ?? 'Servicio facturado',
            'quantity' => $overrides['quantity'] ?? 1,
            'unit_price' => $overrides['unit_price'] ?? 100000,
            'taxable_base' => $overrides['taxable_base'] ?? 100000,
            'tax_total' => $overrides['tax_total'] ?? 19000,
            'line_total' => $overrides['line_total'] ?? 100000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assistantConversationFor(User $user, array $overrides = []): AssistantConversation
    {
        $conversation = new AssistantConversation($overrides);
        $conversation->company_id = $user->company_id;
        $conversation->user_id = $overrides['user_id'] ?? $user->id;
        $conversation->status = $overrides['status'] ?? 'resolved';
        $conversation->save();

        return $conversation;
    }
}
