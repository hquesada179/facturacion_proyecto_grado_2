<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Reports\DocumentReportService;
use App\Services\Reports\ReportDateRange;
use Illuminate\Support\Facades\Gate;

/**
 * Thin wrapper around DocumentReportService::summary() — the exact same
 * source of truth the Reportes screen uses for gross/tax/credited/net.
 * Never recalculates those numbers differently; only resolves which date
 * range to ask for based on the user's wording.
 */
class GetBillingSummaryTool
{
    public function __construct(private readonly DocumentReportService $documents) {}

    public function handle(User $user, ?ReportDateRange $range = null): array
    {
        if (Gate::forUser($user)->denies('view-reports')) {
            return ['tool' => 'billing_summary', 'allowed' => false];
        }

        $range ??= ReportDateRange::fromFilters(['date_range' => ReportDateRange::DEFAULT]);
        $summary = $this->documents->summary($user, [], $range);

        return [
            'tool' => 'billing_summary',
            'allowed' => true,
            'period_key' => $range->key,
            'period_label' => $range->label,
            'summary' => $summary,
        ];
    }
}
