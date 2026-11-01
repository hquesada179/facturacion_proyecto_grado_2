<?php

namespace App\Services\Assistant\Tools;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Reports\ReportQueryFactory;
use Illuminate\Support\Facades\Gate;

/**
 * Recent invoices/credit notes stuck in a rejected or technical-error
 * state. Same "own documents for Facturador" scoping as the Reportes
 * module's error metrics — never wider than what that screen already
 * shows the same user.
 */
class GetDocumentErrorsTool
{
    private const LIMIT = 10;

    public function __construct(private readonly ReportQueryFactory $queries) {}

    public function handle(User $user): array
    {
        if (Gate::forUser($user)->denies('view-reports')) {
            return ['tool' => 'document_errors', 'allowed' => false];
        }

        $invoiceQuery = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', [InvoiceStatus::SimulatedRejected->value, InvoiceStatus::TechnicalError->value]);

        $creditNoteQuery = CreditNote::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', [CreditNoteStatus::SimulatedRejected->value, CreditNoteStatus::TechnicalError->value]);

        if ($this->queries->mustLimitToOwnDocuments($user)) {
            $invoiceQuery->where('user_id', $user->id);
            $creditNoteQuery->where('user_id', $user->id);
        }

        $totalMatches = (clone $invoiceQuery)->count() + (clone $creditNoteQuery)->count();

        $documents = $invoiceQuery->with('customer')->latest('updated_at')->limit(self::LIMIT)->get()
            ->map(fn (Invoice $invoice): array => [
                'type' => 'invoice',
                'id' => $invoice->id,
                'number' => $invoice->number ?: 'Borrador #'.$invoice->id,
                'customer' => $invoice->customer?->name,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'total' => (float) $invoice->total,
                'reason' => $invoice->dian_simulation_message,
                'date' => $invoice->updated_at?->toDateTimeString(),
            ])
            ->concat(
                $creditNoteQuery->with('invoice.customer')->latest('updated_at')->limit(self::LIMIT)->get()
                    ->map(fn (CreditNote $creditNote): array => [
                        'type' => 'credit_note',
                        'id' => $creditNote->id,
                        'number' => $creditNote->number ?: 'Borrador NC #'.$creditNote->id,
                        'customer' => $creditNote->invoice?->customer?->name,
                        'status' => $creditNote->status->value,
                        'status_label' => $creditNote->status->label(),
                        'total' => (float) $creditNote->total,
                        'reason' => $creditNote->dian_simulation_message,
                        'date' => $creditNote->updated_at?->toDateTimeString(),
                    ])
            )
            ->sortByDesc('date')
            ->take(self::LIMIT)
            ->values();

        return [
            'tool' => 'document_errors',
            'allowed' => true,
            'total_matches' => $totalMatches,
            'documents' => $documents->all(),
        ];
    }
}
