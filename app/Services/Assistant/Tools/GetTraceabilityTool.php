<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GetTraceabilityTool
{
    private const LIMIT = 20;

    /**
     * $invoiceId is the current-screen context; $number is an explicit
     * reference parsed from the user's own message (e.g. "FV-000002").
     * An explicit number always takes priority over local context — see
     * the IA 2.0-B report's priority rule.
     */
    public function handle(User $user, ?int $invoiceId = null, ?string $number = null): array
    {
        if (Gate::forUser($user)->denies('view-traceability')) {
            return [
                'tool' => 'traceability',
                'allowed' => false,
                'events' => [],
            ];
        }

        $invoice = $this->resolveInvoice($user, $invoiceId, $number);

        if (! $invoice || Gate::forUser($user)->denies('view', $invoice)) {
            return [
                'tool' => 'traceability',
                'allowed' => true,
                'found' => false,
                'events' => [],
            ];
        }

        return [
            'tool' => 'traceability',
            'allowed' => true,
            'found' => true,
            'invoice_number' => $invoice->number ?: 'Borrador #'.$invoice->id,
            'total_events' => $invoice->events()->count(),
            'events' => $invoice->events()
                ->with('user')
                ->oldest()
                ->limit(self::LIMIT)
                ->get()
                ->map(fn ($event): array => [
                    'type' => $event->type,
                    'description' => $event->description,
                    'from_status' => $event->from_status ?? 'n/a',
                    'to_status' => $event->to_status ?? 'n/a',
                    'user' => $event->user?->name ?? 'Sistema',
                    'date' => $event->created_at->format('Y-m-d H:i'),
                ])
                ->all(),
        ];
    }

    private function resolveInvoice(User $user, ?int $invoiceId, ?string $number): ?Invoice
    {
        $query = Invoice::withoutGlobalScopes()->where('company_id', $user->company_id);

        if ($number !== null && trim($number) !== '') {
            return $query->where('number', trim($number))->first();
        }

        if ($invoiceId !== null) {
            return $query->whereKey($invoiceId)->first();
        }

        return null;
    }
}
