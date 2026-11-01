<?php

namespace App\Services\Assistant\Tools;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Resolves "facturas/notas crédito de <cliente>" and the local-context
 * "¿qué documentos tiene este cliente?" quick action. Never guesses: if a
 * name search matches more than one customer, returns the candidate list
 * instead of picking one (see IA 2.0-B report, "resolución de ambigüedad").
 */
class GetCustomerInvoicesTool
{
    private const DOCUMENT_LIMIT = 10;

    private const AMBIGUITY_LIMIT = 5;

    public function handle(User $user, ?string $term = null, ?int $customerId = null): array
    {
        $customer = $customerId !== null
            ? $this->resolveById($user, $customerId)
            : null;

        if ($customerId !== null) {
            if (! $customer) {
                return ['tool' => 'customer_documents', 'found' => false, 'ambiguous' => false];
            }

            return $this->documentsFor($user, $customer);
        }

        $term = trim((string) $term);

        if ($term === '') {
            return ['tool' => 'customer_documents', 'found' => false, 'ambiguous' => false];
        }

        $matches = Customer::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('identification_number', 'like', '%'.$term.'%');
            })
            ->limit(self::AMBIGUITY_LIMIT + 1)
            ->get()
            ->filter(fn (Customer $customer): bool => Gate::forUser($user)->allows('view', $customer))
            ->values();

        if ($matches->isEmpty()) {
            return ['tool' => 'customer_documents', 'found' => false, 'ambiguous' => false];
        }

        if ($matches->count() > 1) {
            return [
                'tool' => 'customer_documents',
                'found' => false,
                'ambiguous' => true,
                'candidates' => $matches->take(self::AMBIGUITY_LIMIT)->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'identification' => $customer->identification_type.' '.$customer->identification_number,
                ])->values()->all(),
            ];
        }

        return $this->documentsFor($user, $matches->first());
    }

    private function resolveById(User $user, int $customerId): ?Customer
    {
        $customer = Customer::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($customerId)
            ->first();

        return $customer && Gate::forUser($user)->allows('view', $customer) ? $customer : null;
    }

    private function documentsFor(User $user, Customer $customer): array
    {
        $invoicesQuery = $customer->invoices();
        $invoicesTotalMatches = $invoicesQuery->count();

        $invoices = $customer->invoices()
            ->latest('updated_at')
            ->limit(self::DOCUMENT_LIMIT)
            ->get()
            ->filter(fn ($invoice): bool => Gate::forUser($user)->allows('view', $invoice))
            ->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number ?: 'Borrador #'.$invoice->id,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'total' => (float) $invoice->total,
                'date' => ($invoice->issued_at ?? $invoice->updated_at)?->toDateTimeString(),
            ])
            ->values();

        $creditNoteBaseQuery = CreditNote::withoutGlobalScopes()
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id));

        $creditNotesTotalMatches = (clone $creditNoteBaseQuery)->count();

        $creditNotes = (clone $creditNoteBaseQuery)
            ->with('invoice')
            ->latest('updated_at')
            ->limit(self::DOCUMENT_LIMIT)
            ->get()
            ->filter(fn (CreditNote $creditNote): bool => Gate::forUser($user)->allows('view', $creditNote))
            ->map(fn (CreditNote $creditNote): array => [
                'id' => $creditNote->id,
                'number' => $creditNote->number ?: 'Borrador NC #'.$creditNote->id,
                'invoice_number' => $creditNote->invoice?->number,
                'status' => $creditNote->status->value,
                'status_label' => $creditNote->status->label(),
                'total' => (float) $creditNote->total,
                'date' => ($creditNote->issued_at ?? $creditNote->updated_at)?->toDateTimeString(),
            ])
            ->values();

        $billed = (float) $customer->invoices()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyCredited->value, InvoiceStatus::Voided->value])
            ->sum('total');

        $credited = (float) (clone $creditNoteBaseQuery)
            ->where('status', CreditNoteStatus::Issued->value)
            ->sum('total');

        return [
            'tool' => 'customer_documents',
            'found' => true,
            'ambiguous' => false,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
            'invoices_total_matches' => $invoicesTotalMatches,
            'invoices' => $invoices->all(),
            'credit_notes_total_matches' => $creditNotesTotalMatches,
            'credit_notes' => $creditNotes->all(),
            'totals' => [
                'billed' => $billed,
                'credited' => $credited,
                'net' => $billed - $credited,
            ],
        ];
    }
}
