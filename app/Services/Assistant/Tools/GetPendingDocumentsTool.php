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
 * Invoices/credit notes not yet issued (draft, locally validated, or
 * "sending simulated"). Same pending-status set DashboardMetricsService
 * already uses, with the same own-documents scoping as the Reportes
 * module for a Facturador.
 */
class GetPendingDocumentsTool
{
    private const LIMIT = 10;

    private const PENDING_INVOICE_STATUSES = [
        InvoiceStatus::Draft->value,
        InvoiceStatus::LocallyValidated->value,
        InvoiceStatus::SendingSimulated->value,
    ];

    private const PENDING_CREDIT_NOTE_STATUSES = [
        CreditNoteStatus::Draft->value,
        CreditNoteStatus::LocallyValidated->value,
        CreditNoteStatus::SendingSimulated->value,
    ];

    public function __construct(private readonly ReportQueryFactory $queries) {}

    public function handle(User $user): array
    {
        if (Gate::forUser($user)->denies('view-reports')) {
            return ['tool' => 'pending_documents', 'allowed' => false];
        }

        $invoiceQuery = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', self::PENDING_INVOICE_STATUSES);

        $creditNoteQuery = CreditNote::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereIn('status', self::PENDING_CREDIT_NOTE_STATUSES);

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
                        'date' => $creditNote->updated_at?->toDateTimeString(),
                    ])
            )
            ->sortByDesc('date')
            ->take(self::LIMIT)
            ->values();

        return [
            'tool' => 'pending_documents',
            'allowed' => true,
            'total_matches' => $totalMatches,
            'documents' => $documents->all(),
        ];
    }
}
