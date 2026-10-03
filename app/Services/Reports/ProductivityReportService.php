<?php

namespace App\Services\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ReportFormatter;
use Illuminate\Support\Collection;

class ProductivityReportService
{
    public function __construct(
        private readonly ReportQueryFactory $queries,
        private readonly ErrorReportService $errors,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dataFor(User $user, array $filters, ReportDateRange $range): array
    {
        $issuedStatuses = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyCredited->value,
            InvoiceStatus::Voided->value,
        ];

        $issuedInvoices = $this->queries->includeInvoices($filters)
            ? $this->queries->invoices($user, $filters, $range, 'issued_at')
                ->whereIn('status', $issuedStatuses)
                ->get(['id', 'created_at', 'issued_at'])
            : collect();

        $issuedCreditNotes = $this->queries->includeCreditNotes($filters)
            ? $this->queries->creditNotes($user, $filters, $range, 'issued_at')
                ->where('status', CreditNoteStatus::Issued->value)
                ->count()
            : 0;

        $durations = $issuedInvoices
            ->filter(fn (Invoice $invoice): bool => $invoice->created_at !== null && $invoice->issued_at !== null)
            ->map(fn (Invoice $invoice): int|float => abs($invoice->created_at->diffInSeconds($invoice->issued_at)))
            ->sort()
            ->values();

        $events = $this->errors->events($user, $filters, $range);
        $validationAttempts = $events->whereIn('type', ['local_validation_failed', 'local_validation_passed'])->count();
        $validationSuccesses = $events->where('type', 'local_validation_passed')->count();
        $rejections = $events->whereIn('type', ['simulated_validation_rejected', 'credit_note_simulated_rejected'])->count();
        $technicalErrors = $events->whereIn('type', ['technical_error', 'credit_note_technical_error'])->count();
        $reprocesses = $events->where('type', 'draft_reopened')->count();
        $blocking = $events->sum(fn ($event): int => (int) (($event->metadata['blocking_count'] ?? data_get($event->metadata, 'results.blocking_count')) ?? 0));

        $documentsEmitted = $issuedInvoices->count() + $issuedCreditNotes;
        $userDivisor = $this->queries->mustLimitToOwnDocuments($user)
            ? 1
            : max(1, $this->queries->usersForCompany($user)->count());
        $rejectionBase = max(1, $documentsEmitted + $rejections + $technicalErrors);
        $invoiceBase = max(1, $this->queries->includeInvoices($filters) ? $this->queries->invoices($user, $filters, $range)->count() : 0);

        $raw = [
            'average_emission_seconds' => $durations->isEmpty() ? null : (float) $durations->avg(),
            'median_emission_seconds' => $this->median($durations),
            'documents_emitted' => $documentsEmitted,
            'documents_per_user' => $documentsEmitted / $userDivisor,
            'validation_success_rate' => $validationAttempts > 0 ? ($validationSuccesses / $validationAttempts) * 100 : 0.0,
            'rejected_percentage' => (($rejections + $technicalErrors) / $rejectionBase) * 100,
            'reprocesses' => $reprocesses,
            'average_errors_per_invoice' => $blocking / $invoiceBase,
            'credit_note_corrections' => $issuedCreditNotes,
        ];

        return [
            'metrics' => $raw,
            'cards' => [
                ['label' => 'Promedio de emisión', 'value' => ReportFormatter::duration($raw['average_emission_seconds']), 'icon' => 'timer', 'tone' => 'primary'],
                ['label' => 'Mediana de emisión', 'value' => ReportFormatter::duration($raw['median_emission_seconds']), 'icon' => 'speed', 'tone' => 'secondary'],
                ['label' => 'Documentos emitidos', 'value' => ReportFormatter::integer($raw['documents_emitted']), 'icon' => 'task_alt', 'tone' => 'primary'],
                ['label' => 'Documentos por usuario', 'value' => number_format($raw['documents_per_user'], 2, ',', '.'), 'icon' => 'groups', 'tone' => 'secondary'],
                ['label' => 'Éxito de validación', 'value' => ReportFormatter::percent($raw['validation_success_rate']), 'icon' => 'verified', 'tone' => 'secondary'],
                ['label' => 'Rechazos y errores', 'value' => ReportFormatter::percent($raw['rejected_percentage']), 'icon' => 'gpp_bad', 'tone' => 'error'],
                ['label' => 'Reprocesos', 'value' => ReportFormatter::integer($raw['reprocesses']), 'icon' => 'refresh', 'tone' => 'warning'],
                ['label' => 'Errores promedio/factura', 'value' => number_format($raw['average_errors_per_invoice'], 2, ',', '.'), 'icon' => 'rule_folder', 'tone' => 'warning'],
                ['label' => 'Correcciones con nota crédito', 'value' => ReportFormatter::integer($raw['credit_note_corrections']), 'icon' => 'assignment_return', 'tone' => 'secondary'],
            ],
        ];
    }

    /**
     * @param  Collection<int, int|float>  $values
     */
    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $count = $values->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}
