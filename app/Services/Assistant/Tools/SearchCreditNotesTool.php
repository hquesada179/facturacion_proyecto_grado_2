<?php

namespace App\Services\Assistant\Tools;

use App\Models\CreditNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * General-purpose credit note search — by customer name, origin invoice
 * number, or status — as opposed to GetCreditNoteDetailsTool, which only
 * resolves a single credit note by its own id/number.
 */
class SearchCreditNotesTool
{
    private const LIMIT = 10;

    /**
     * @param  array<string, mixed>  $filters  Optional keys: status,
     *   customer_term, invoice_number.
     */
    public function handle(User $user, array $filters = []): array
    {
        $query = CreditNote::withoutGlobalScopes()
            ->with('invoice.customer')
            ->where('company_id', $user->company_id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['invoice_number'])) {
            $invoiceNumber = $filters['invoice_number'];
            $query->whereHas('invoice', fn ($q) => $q->where('number', $invoiceNumber));
        }

        if (! empty($filters['customer_term'])) {
            $term = $filters['customer_term'];
            $query->whereHas('invoice.customer', fn ($q) => $q->where('name', 'like', '%'.$term.'%'));
        }

        $totalMatches = (clone $query)->count();

        $matches = $query->latest('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (CreditNote $creditNote): bool => Gate::forUser($user)->allows('view', $creditNote))
            ->map(fn (CreditNote $creditNote): array => [
                'id' => $creditNote->id,
                'number' => $creditNote->number ?: 'Borrador NC #'.$creditNote->id,
                'invoice_number' => $creditNote->invoice?->number,
                'customer' => $creditNote->invoice?->customer?->name,
                'status' => $creditNote->status->value,
                'status_label' => $creditNote->status->label(),
                'reason' => $creditNote->reason_text ?: $creditNote->reason,
                'total' => (float) $creditNote->total,
                'date' => ($creditNote->issued_at ?? $creditNote->updated_at)?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'tool' => 'search_credit_notes',
            'total_matches' => $totalMatches,
            'matches' => $matches,
        ];
    }
}
