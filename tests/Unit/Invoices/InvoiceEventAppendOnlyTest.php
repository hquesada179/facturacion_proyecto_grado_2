<?php

namespace Tests\Unit\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvoiceEventAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function eventFor(Invoice $invoice): InvoiceEvent
    {
        return InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'type' => 'draft_created',
            'from_status' => null,
            'to_status' => 'draft',
            'description' => 'Se creó un nuevo borrador de factura.',
        ]);
    }

    public function test_an_existing_event_cannot_be_modified(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $company->id;
        $invoice->save();

        $event = $this->eventFor($invoice);

        $event->description = 'Intento de alterar la bitácora.';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('append-only');
        $event->save();
    }

    public function test_an_existing_event_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $company->id;
        $invoice->save();

        $event = $this->eventFor($invoice);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('append-only');
        $event->delete();
    }

    public function test_events_always_capture_actor_and_state_transition_metadata(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $company->id;
        $invoice->save();

        $event = InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'type' => 'customer_selected',
            'from_status' => 'draft',
            'to_status' => 'draft',
            'description' => 'Se seleccionó el cliente.',
            'metadata' => ['customer_id' => 42],
        ]);

        $this->assertNotNull($event->created_at);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('draft', $event->from_status);
        $this->assertSame('draft', $event->to_status);
        $this->assertSame(['customer_id' => 42], $event->metadata);
    }

    public function test_there_is_no_route_to_update_or_delete_a_trace_event(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse($routes->contains(fn (string $uri): bool => str_contains($uri, 'invoice-events') || str_contains($uri, 'eventos')));
    }
}
