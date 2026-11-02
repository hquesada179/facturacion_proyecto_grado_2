<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Reports\DocumentReportService;
use App\Services\Reports\ReportDateRange;
use Illuminate\Support\Facades\Gate;

/**
 * "¿Qué cliente tiene más facturación?" / "¿Qué producto se ha facturado
 * más?" — a thin wrapper around DocumentReportService::byClients()/
 * byProducts(), the exact same ranking the Reportes screen already shows.
 * Never computes its own ranking; only reads and takes the top entries.
 *
 * "¿Cuál fue el mes con más facturación?" is NOT implemented here — no
 * existing service aggregates totals by month across an arbitrary year
 * for comparison, and building that is a new analytical layer out of
 * scope for this phase (see the IA 2.0-C report, deferred to IA 2.0-D).
 */
class GetRankingTool
{
    public function __construct(private readonly DocumentReportService $documents) {}

    public function handle(User $user, string $type, ?ReportDateRange $range = null): array
    {
        if (Gate::forUser($user)->denies('view-reports')) {
            return ['tool' => 'ranking', 'allowed' => false];
        }

        $range ??= ReportDateRange::fromFilters(['date_range' => ReportDateRange::DEFAULT]);

        $rows = $type === 'products'
            ? $this->documents->byProducts($user, [], $range)
            : $this->documents->byClients($user, [], $range);

        return [
            'tool' => 'ranking',
            'allowed' => true,
            'type' => $type,
            'period_label' => $range->label,
            'items' => $rows->take(5)->values()->all(),
        ];
    }
}
