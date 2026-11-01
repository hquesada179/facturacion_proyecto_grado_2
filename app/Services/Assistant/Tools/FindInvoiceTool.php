<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FindInvoiceTool
{
    private const MAX_LIMIT = 10;

    /**
     * @param  array<string, mixed>  $filters  Optional keys: status,
     *   customer_term, customer_id, date_from, date_to. customer_id is an
     *   already-disambiguated exact match (used after resolving a name via
     *   GetCustomerInvoicesTool::resolveCustomer()); customer_term is a
     *   plain LIKE match for free-text search.
     */
    public function handle(User $user, ?string $term, array $filters = [], int $limit = 5): array
    {
        $term = trim((string) $term);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query = Invoice::withoutGlobalScopes()
            ->with('customer')
            ->where('company_id', $user->company_id);

        if ($term !== '') {
            $query->where(function ($query) use ($term): void {
                $query->where('number', 'like', '%'.$term.'%');

                if (is_numeric($term)) {
                    $query->orWhere('id', (int) $term);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_term'])) {
            $customerTerm = $filters['customer_term'];
            $query->whereHas('customer', fn ($q) => $q->where('name', 'like', '%'.$customerTerm.'%'));
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $totalMatches = (clone $query)->count();

        $matches = $query->latest('updated_at')->limit($limit)->get()
            ->filter(fn (Invoice $invoice): bool => Gate::forUser($user)->allows('view', $invoice))
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'total' => (float) $invoice->total,
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'tool' => 'find_invoice',
            'total_matches' => $totalMatches,
            'matches' => $matches,
        ];
    }
}
