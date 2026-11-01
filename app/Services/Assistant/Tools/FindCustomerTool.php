<?php

namespace App\Services\Assistant\Tools;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FindCustomerTool
{
    private const MAX_LIMIT = 10;

    /**
     * @param  array<string, mixed>  $filters  Optional key: status.
     */
    public function handle(User $user, ?string $term, array $filters = [], int $limit = 10): array
    {
        $term = trim((string) $term);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query = Customer::withoutGlobalScopes()
            ->withCount('invoices')
            ->where('company_id', $user->company_id)
            ->orderBy('name');

        if ($term !== '') {
            // A single free-text term matches name, document, email or
            // city — the same "one search box" behaviour as a global
            // search, so "Busca clientes de Bucaramanga" works without
            // needing to classify which field the word refers to.
            $query->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('identification_number', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('city', 'like', '%'.$term.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $totalMatches = (clone $query)->count();

        $matches = $query->limit($limit)->get()
            ->filter(fn (Customer $customer): bool => Gate::forUser($user)->allows('view', $customer))
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'identification' => $customer->identification_type.' '.$customer->identification_number,
                'city' => $customer->city,
                'status' => Customer::STATUSES[$customer->status] ?? $customer->status,
                'documents_count' => $customer->invoices_count,
            ])
            ->values()
            ->all();

        return [
            'tool' => 'find_customer',
            'total_matches' => $totalMatches,
            'matches' => $matches,
        ];
    }
}
