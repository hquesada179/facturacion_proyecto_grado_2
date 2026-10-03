<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GetTraceabilityTool
{
    public function handle(User $user, int $invoiceId): array
    {
        if (Gate::forUser($user)->denies('view-traceability')) {
            return [
                'tool' => 'traceability',
                'allowed' => false,
                'events' => [],
            ];
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($invoiceId)
            ->first();

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
            'events' => $invoice->events()
                ->with('user')
                ->oldest()
                ->limit(15)
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
}
