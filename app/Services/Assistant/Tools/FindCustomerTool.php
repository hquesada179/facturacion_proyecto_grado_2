<?php

namespace App\Services\Assistant\Tools;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FindCustomerTool
{
    public function handle(User $user, ?string $term): array
    {
        $term = trim((string) $term);

        $query = Customer::withoutGlobalScopes()
            ->withCount('invoices')
            ->where('company_id', $user->company_id)
            ->orderBy('name');

        if ($term !== '') {
            $query->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('identification_number', 'like', '%'.$term.'%');
            });
        }

        $matches = $query->limit(5)->get()
            ->filter(fn (Customer $customer): bool => Gate::forUser($user)->allows('view', $customer))
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'identification' => $customer->identification_type.' '.$customer->identification_number,
                'status' => Customer::STATUSES[$customer->status] ?? $customer->status,
                'documents_count' => $customer->invoices_count,
            ])
            ->values()
            ->all();

        return [
            'tool' => 'find_customer',
            'matches' => $matches,
        ];
    }
}
