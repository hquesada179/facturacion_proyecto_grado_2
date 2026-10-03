<?php

namespace App\Services\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\AssistantConversation;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ReportFormatter;
use Illuminate\Support\Collection;

class DashboardMetricsService
{
    public function __construct(private readonly ReportQueryFactory $queries) {}

    /**
     * @return array<string, mixed>
     */
    public function dataFor(User $user): array
    {
        $invoiceQuery = $this->invoiceBaseQuery($user);
        $creditNoteQuery = $this->creditNoteBaseQuery($user);
        $monthStart = now()->startOfMonth();

        $emittedStatuses = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyCredited->value,
            InvoiceStatus::Voided->value,
        ];

        $errorStatuses = [
            InvoiceStatus::SimulatedRejected->value,
            InvoiceStatus::TechnicalError->value,
        ];

        $pendingStatuses = [
            InvoiceStatus::Draft->value,
            InvoiceStatus::LocallyValidated->value,
            InvoiceStatus::SendingSimulated->value,
        ];

        $emitted = (clone $invoiceQuery)->whereIn('status', $emittedStatuses)->count();
        $emittedThisMonth = (clone $invoiceQuery)
            ->whereIn('status', $emittedStatuses)
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', $monthStart)
            ->count();
        $pending = (clone $invoiceQuery)->whereIn('status', $pendingStatuses)->count();
        $errors = (clone $invoiceQuery)->whereIn('status', $errorStatuses)->count();
        $creditNotes = (clone $creditNoteQuery)->where('status', CreditNoteStatus::Issued->value)->count();
        $averageEmissionSeconds = $this->averageEmissionSeconds(
            (clone $invoiceQuery)->whereNotNull('issued_at')->whereIn('status', $emittedStatuses)->get(['created_at', 'issued_at'])
        );

        $assistantResolved = $this->assistantBaseQuery($user)
            ->whereIn('status', ['resolved', 'closed', 'completed'])
            ->count();

        return [
            'metrics' => [
                [
                    'label' => 'Facturas emitidas',
                    'value' => ReportFormatter::integer($emitted),
                    'icon' => 'receipt_long',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Emitidas este mes',
                    'value' => ReportFormatter::integer($emittedThisMonth),
                    'icon' => 'calendar_month',
                    'tone' => 'secondary',
                ],
                [
                    'label' => 'Pendientes y borradores',
                    'value' => ReportFormatter::integer($pending),
                    'icon' => 'pending_actions',
                    'tone' => 'warning',
                ],
                [
                    'label' => 'Errores o rechazos',
                    'value' => ReportFormatter::integer($errors),
                    'icon' => 'error',
                    'tone' => 'error',
                ],
                [
                    'label' => 'Notas crédito emitidas',
                    'value' => ReportFormatter::integer($creditNotes),
                    'icon' => 'assignment_return',
                    'tone' => 'secondary',
                ],
                [
                    'label' => 'Tiempo promedio de emisión',
                    'value' => ReportFormatter::duration($averageEmissionSeconds),
                    'icon' => 'timer',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Asistente resuelto',
                    'value' => ReportFormatter::integer($assistantResolved),
                    'icon' => 'smart_toy',
                    'tone' => 'secondary',
                    'hint' => $assistantResolved === 0 ? 'No disponible si no hay conversaciones reales cerradas.' : null,
                ],
            ],
            'recentDocuments' => $this->recentDocuments($user),
            'isLimitedToOwnDocuments' => $this->queries->mustLimitToOwnDocuments($user),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentDocuments(User $user): Collection
    {
        $invoices = $this->invoiceBaseQuery($user)
            ->with('customer')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'type' => 'Factura',
                'number' => $invoice->number ?: 'Borrador #'.$invoice->id,
                'customer' => $invoice->customer?->name ?? 'Cliente sin asociar',
                'status' => $invoice->status->label(),
                'total' => ReportFormatter::money($invoice->total),
                'date' => ($invoice->issued_at ?? $invoice->updated_at ?? $invoice->created_at)?->format('d/m/Y H:i'),
                'sort_date' => $invoice->issued_at ?? $invoice->updated_at ?? $invoice->created_at,
                'href' => route('invoices.show', $invoice),
                'icon' => 'visibility',
            ]);

        $creditNotes = $this->creditNoteBaseQuery($user)
            ->with('invoice.customer')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (CreditNote $creditNote): array => [
                'type' => 'Nota crédito',
                'number' => $creditNote->number ?: 'Borrador NC #'.$creditNote->id,
                'customer' => $creditNote->invoice?->customer?->name ?? 'Cliente sin asociar',
                'status' => $creditNote->status->label(),
                'total' => ReportFormatter::money($creditNote->total),
                'date' => ($creditNote->issued_at ?? $creditNote->updated_at ?? $creditNote->created_at)?->format('d/m/Y H:i'),
                'sort_date' => $creditNote->issued_at ?? $creditNote->updated_at ?? $creditNote->created_at,
                'href' => route('credit-notes.show', $creditNote),
                'icon' => 'visibility',
            ]);

        return $invoices
            ->concat($creditNotes)
            ->sortByDesc('sort_date')
            ->take(8)
            ->values();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function averageEmissionSeconds(Collection $invoices): ?float
    {
        $durations = $invoices
            ->filter(fn (Invoice $invoice): bool => $invoice->created_at !== null && $invoice->issued_at !== null)
            ->map(fn (Invoice $invoice): int|float => abs($invoice->created_at->diffInSeconds($invoice->issued_at)));

        return $durations->isEmpty() ? null : (float) $durations->avg();
    }

    private function invoiceBaseQuery(User $user): mixed
    {
        $query = Invoice::withoutGlobalScopes()->where('company_id', $user->company_id);

        if ($this->queries->mustLimitToOwnDocuments($user)) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function creditNoteBaseQuery(User $user): mixed
    {
        $query = CreditNote::withoutGlobalScopes()->where('company_id', $user->company_id);

        if ($this->queries->mustLimitToOwnDocuments($user)) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function assistantBaseQuery(User $user): mixed
    {
        $query = AssistantConversation::withoutGlobalScopes()->where('company_id', $user->company_id);

        if ($this->queries->mustLimitToOwnDocuments($user)) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }
}
