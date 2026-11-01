<?php

namespace App\Services\Assistant\Tools;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProductStatus;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Reports\ReportQueryFactory;
use Illuminate\Support\Facades\Gate;

/**
 * Deterministic counts only — every number comes from a COUNT()/pluck()
 * query against company-scoped data, never estimated by the model.
 * Mirrors the same "own documents only for Facturador" rule the real
 * Reportes screen already enforces (ReportQueryFactory::mustLimitToOwnDocuments)
 * so the assistant never reveals more than that screen would for the same
 * user — counts are never widened "for convenience".
 */
class GetDocumentCountsTool
{
    public function __construct(private readonly ReportQueryFactory $queries) {}

    public function handle(User $user): array
    {
        if (Gate::forUser($user)->denies('view-reports')) {
            return ['tool' => 'document_counts', 'allowed' => false];
        }

        $invoiceQuery = Invoice::withoutGlobalScopes()->where('company_id', $user->company_id);
        $creditNoteQuery = CreditNote::withoutGlobalScopes()->where('company_id', $user->company_id);
        $ownOnly = $this->queries->mustLimitToOwnDocuments($user);

        if ($ownOnly) {
            $invoiceQuery->where('user_id', $user->id);
            $creditNoteQuery->where('user_id', $user->id);
        }

        $invoiceCounts = (clone $invoiceQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $creditNoteCounts = (clone $creditNoteQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoicesByStatus = collect(InvoiceStatus::cases())
            ->mapWithKeys(fn (InvoiceStatus $status): array => [
                $status->value => (int) ($invoiceCounts[$status->value] ?? 0),
            ]);

        $creditNotesByStatus = collect(CreditNoteStatus::cases())
            ->mapWithKeys(fn (CreditNoteStatus $status): array => [
                $status->value => (int) ($creditNoteCounts[$status->value] ?? 0),
            ]);

        return [
            'tool' => 'document_counts',
            'allowed' => true,
            'scope' => $ownOnly ? 'own' : 'company',
            'invoices_total' => (int) $invoicesByStatus->sum(),
            'invoices_by_status' => $invoicesByStatus->all(),
            'credit_notes_total' => (int) $creditNotesByStatus->sum(),
            'credit_notes_by_status' => $creditNotesByStatus->all(),
            'customers_total' => Customer::withoutGlobalScopes()->where('company_id', $user->company_id)->count(),
            'products_total' => ProductService::withoutGlobalScopes()->where('company_id', $user->company_id)->count(),
            'products_active' => ProductService::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->where('status', ProductStatus::Active->value)
                ->count(),
        ];
    }
}
