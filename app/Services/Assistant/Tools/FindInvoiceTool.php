<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FindInvoiceTool
{
    public function handle(User $user, ?string $term): array
    {
        $term = trim((string) $term);

        $query = Invoice::withoutGlobalScopes()
            ->with('customer')
            ->where('company_id', $user->company_id)
            ->latest('updated_at');

        if ($term !== '') {
            $query->where(function ($query) use ($term): void {
                $query->where('number', 'like', '%'.$term.'%');

                if (is_numeric($term)) {
                    $query->orWhere('id', (int) $term);
                }
            });
        }

        $matches = $query->limit(5)->get()
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
            'matches' => $matches,
        ];
    }
}
