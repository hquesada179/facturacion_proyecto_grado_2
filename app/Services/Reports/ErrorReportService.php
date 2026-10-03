<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\InvoiceEvent;
use App\Models\User;
use App\Support\ReportFormatter;
use Illuminate\Support\Collection;

class ErrorReportService
{
    public function __construct(private readonly ReportQueryFactory $queries) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dataFor(User $user, array $filters, ReportDateRange $range): array
    {
        $events = $this->events($user, $filters, $range);

        $validations = $events->whereIn('type', ['local_validation_failed', 'local_validation_passed']);
        $validationAttempts = $validations->count();
        $successfulValidations = $events->where('type', 'local_validation_passed')->count();
        $failedValidations = $events->where('type', 'local_validation_failed')->count();
        $simulatedRejections = $events->whereIn('type', ['simulated_validation_rejected', 'credit_note_simulated_rejected'])->count();
        $technicalErrors = $events->whereIn('type', ['technical_error', 'credit_note_technical_error'])->count();

        $blocking = $events->sum(fn (InvoiceEvent $event): int => $this->metadataCount($event, 'blocking_count'));
        $warnings = $events->sum(fn (InvoiceEvent $event): int => $this->metadataCount($event, 'warning_count'));

        $metrics = [
            'validation_attempts' => $validationAttempts,
            'successful_validations' => $successfulValidations,
            'failed_validations' => $failedValidations,
            'blocking_issues' => $blocking,
            'warnings' => $warnings,
            'simulated_rejections' => $simulatedRejections,
            'technical_errors' => $technicalErrors,
        ];

        return [
            'metrics' => $metrics,
            'cards' => [
                ['label' => 'Validaciones locales', 'value' => ReportFormatter::integer($validationAttempts), 'icon' => 'rule', 'tone' => 'primary'],
                ['label' => 'Validaciones exitosas', 'value' => ReportFormatter::integer($successfulValidations), 'icon' => 'check_circle', 'tone' => 'secondary'],
                ['label' => 'Bloqueos detectados', 'value' => ReportFormatter::integer($blocking), 'icon' => 'block', 'tone' => 'error'],
                ['label' => 'Advertencias', 'value' => ReportFormatter::integer($warnings), 'icon' => 'warning', 'tone' => 'warning'],
                ['label' => 'Rechazos simulados', 'value' => ReportFormatter::integer($simulatedRejections), 'icon' => 'cancel', 'tone' => 'error'],
                ['label' => 'Errores técnicos', 'value' => ReportFormatter::integer($technicalErrors), 'icon' => 'report', 'tone' => 'error'],
            ],
            'topFailures' => $this->topFailures($events),
            'recentEvents' => $events->take(12)->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, InvoiceEvent>
     */
    public function events(User $user, array $filters, ReportDateRange $range): Collection
    {
        $query = InvoiceEvent::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_events.invoice_id')
            ->where('invoices.company_id', $user->company_id)
            ->whereBetween('invoice_events.created_at', [$range->start, $range->end])
            ->select('invoice_events.*')
            ->orderByDesc('invoice_events.created_at');

        if ($this->queries->mustLimitToOwnDocuments($user)) {
            $query->where('invoices.user_id', $user->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('invoices.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('invoices.customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['status']) && InvoiceStatus::tryFrom((string) $filters['status'])) {
            $query->where('invoices.status', (string) $filters['status']);
        }

        if (($filters['document_type'] ?? 'all') === 'credit_notes') {
            $query->where('invoice_events.type', 'like', 'credit_note_%');
        } elseif (($filters['document_type'] ?? 'all') === 'invoices') {
            $query->where('invoice_events.type', 'not like', 'credit_note_%');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, InvoiceEvent>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function topFailures(Collection $events): Collection
    {
        $failures = collect();

        $events->each(function (InvoiceEvent $event) use ($failures): void {
            foreach ($this->validationResultsFrom($event) as $result) {
                $code = (string) ($result['codigo'] ?? 'SIN-CODIGO');
                $field = (string) ($result['campo'] ?? 'sin campo');
                $rule = (string) ($result['regla'] ?? $code);
                $severity = (string) ($result['severidad'] ?? 'n/d');
                $key = $code.'|'.$field;
                $current = $failures[$key] ?? [
                    'code' => $code,
                    'field' => $field,
                    'rule' => $rule,
                    'severity' => $severity,
                    'count' => 0,
                ];

                $current['count']++;
                $failures[$key] = $current;
            }
        });

        return $failures
            ->values()
            ->sortByDesc('count')
            ->take(8)
            ->values();
    }

    private function metadataCount(InvoiceEvent $event, string $key): int
    {
        $metadata = $event->metadata ?? [];

        return (int) ($metadata[$key] ?? data_get($metadata, 'results.'.$key, 0));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validationResultsFrom(InvoiceEvent $event): array
    {
        $metadata = $event->metadata ?? [];
        $results = data_get($metadata, 'results.results', $metadata['results'] ?? []);

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_filter($results, fn (mixed $result): bool => is_array($result)));
    }
}
