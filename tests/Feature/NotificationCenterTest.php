<?php

namespace Tests\Feature;

use App\Models\AssistantActionEvent;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI/UX 2.0-C — the bell dropdown must only ever surface events the
 * requesting user is already allowed to see: same company, "own
 * documents only" for Facturador (same rule as ReportQueryFactory /
 * GetDocumentErrorsTool), and assistant actions scoped to their own
 * user regardless of role.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Company $company, ?User $owner = null): Invoice
    {
        $customer = Customer::forceCreate([
            'company_id' => $company->id,
            'identification_type' => 'CC',
            'identification_number' => (string) random_int(100000000, 999999999),
            'name' => 'Cliente de prueba',
        ]);

        return Invoice::forceCreate([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'user_id' => $owner?->id,
            'number' => 'FV-'.random_int(1000, 999999),
            'status' => 'issued',
            'subtotal' => '100.00',
            'tax_total' => '19.00',
            'total' => '119.00',
        ]);
    }

    private function logEvent(Invoice $invoice, string $type, array $metadata = []): InvoiceEvent
    {
        return InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'type' => $type,
            'description' => 'Evento de prueba: '.$type,
            'metadata' => $metadata,
        ]);
    }

    public function test_it_only_surfaces_notable_event_types_and_ignores_noise(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->administrador()->for($company)->create();
        $invoice = $this->makeInvoice($company, $user);

        $this->logEvent($invoice, 'issued');
        $this->logEvent($invoice, 'issuance_requested');
        $this->logEvent($invoice, 'simulated_validation_started');

        $items = app(NotificationCenterService::class)->forUser($user);

        $this->assertCount(1, $items);
        $this->assertSame('Factura emitida', $items[0]['title']);
    }

    public function test_it_never_leaks_events_from_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $userA = User::factory()->administrador()->for($companyA)->create();

        $invoiceA = $this->makeInvoice($companyA, $userA);
        $this->logEvent($invoiceA, 'issued');

        $userB = User::factory()->administrador()->for($companyB)->create();
        $invoiceB = $this->makeInvoice($companyB, $userB);
        $this->logEvent($invoiceB, 'issued');

        $items = app(NotificationCenterService::class)->forUser($userA);

        $this->assertCount(1, $items);
        $this->assertSame(route('invoices.show', $invoiceA->id), $items[0]['url']);
    }

    public function test_facturador_only_sees_events_for_their_own_documents(): void
    {
        $company = Company::factory()->create();
        $facturadorOne = User::factory()->facturador()->for($company)->create();
        $facturadorTwo = User::factory()->facturador()->for($company)->create();

        $ownInvoice = $this->makeInvoice($company, $facturadorOne);
        $this->logEvent($ownInvoice, 'issued');

        $otherInvoice = $this->makeInvoice($company, $facturadorTwo);
        $this->logEvent($otherInvoice, 'issued');

        $items = app(NotificationCenterService::class)->forUser($facturadorOne);

        $this->assertCount(1, $items);
        $this->assertSame(route('invoices.show', $ownInvoice->id), $items[0]['url']);
    }

    public function test_administrador_sees_document_events_across_the_whole_company(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->administrador()->for($company)->create();
        $facturador = User::factory()->facturador()->for($company)->create();

        $invoice = $this->makeInvoice($company, $facturador);
        $this->logEvent($invoice, 'issued');

        $items = app(NotificationCenterService::class)->forUser($admin);

        $this->assertCount(1, $items);
    }

    public function test_credit_note_events_resolve_to_the_credit_note_url(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->administrador()->for($company)->create();
        $invoice = $this->makeInvoice($company, $user);

        $this->logEvent($invoice, 'credit_note_issued', [
            'credit_note_id' => 999,
            'credit_note_number' => 'NC-000999',
        ]);

        $items = app(NotificationCenterService::class)->forUser($user);

        $this->assertCount(1, $items);
        $this->assertSame('Nota crédito emitida', $items[0]['title']);
        $this->assertSame(route('credit-notes.show', 999), $items[0]['url']);
    }

    public function test_assistant_action_events_are_scoped_to_the_requesting_user_only(): void
    {
        $company = Company::factory()->create();
        $userOne = User::factory()->administrador()->for($company)->create();
        $userTwo = User::factory()->administrador()->for($company)->create();

        AssistantActionEvent::forceCreate([
            'company_id' => $company->id,
            'user_id' => $userOne->id,
            'event' => 'assistant_action_confirmed',
            'action' => 'create_customer',
            'context' => [],
            'created_at' => now(),
        ]);

        AssistantActionEvent::forceCreate([
            'company_id' => $company->id,
            'user_id' => $userTwo->id,
            'event' => 'assistant_action_confirmed',
            'action' => 'create_product',
            'context' => [],
            'created_at' => now(),
        ]);

        $items = app(NotificationCenterService::class)->forUser($userOne);

        $this->assertCount(1, $items);
        $this->assertSame('Acción asistida completada', $items[0]['title']);
    }

    public function test_a_user_without_a_company_sees_no_document_notifications(): void
    {
        $user = User::factory()->administrador()->create(['company_id' => null]);

        $items = app(NotificationCenterService::class)->forUser($user);

        $this->assertSame([], $items);
    }
}
