<?php

namespace App\Services\Assistant;

use App\Services\Reports\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Deterministic natural-date-phrase → ReportDateRange resolution for the
 * assistant. Deliberately builds ReportDateRange instances directly via
 * its public constructor instead of extending ReportDateRange::OPTIONS /
 * fromFilters() — those are consumed by the real Reportes screen's filter
 * dropdown, and adding assistant-only phrases there would change that
 * screen's UI. The model never invents a date: every phrase here maps to
 * an explicit, testable start/end pair computed in PHP.
 */
class AssistantDateRangeResolver
{
    private const SPANISH_MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    public function resolve(string $lower): ?ReportDateRange
    {
        $today = CarbonImmutable::now()->startOfDay();

        if (Str::contains($lower, ['ayer'])) {
            $yesterday = $today->subDay();

            return new ReportDateRange($yesterday, $yesterday->endOfDay(), 'yesterday', 'Ayer');
        }

        if (Str::contains($lower, ['hoy', 'el día de hoy', 'el dia de hoy'])) {
            return new ReportDateRange($today, $today->endOfDay(), 'today', 'Hoy');
        }

        if (Str::contains($lower, ['últimos 30 días', 'ultimos 30 dias', 'último mes', 'ultimo mes'])) {
            return new ReportDateRange($today->subDays(29), $today->endOfDay(), 'last_30_days', 'Últimos 30 días');
        }

        if (Str::contains($lower, ['últimos 7 días', 'ultimos 7 dias', 'esta semana', 'última semana', 'ultima semana'])) {
            return new ReportDateRange($today->subDays(6), $today->endOfDay(), 'last_7_days', 'Últimos 7 días');
        }

        if (Str::contains($lower, ['mes pasado', 'el mes pasado', 'mes anterior'])) {
            $start = $today->subMonthNoOverflow()->startOfMonth();
            $end = $start->endOfMonth();

            return new ReportDateRange($start, $end, 'last_month', 'Mes pasado');
        }

        if (Str::contains($lower, ['este año', 'este ano', 'del año', 'del ano', 'en lo que va del año'])) {
            return new ReportDateRange($today->startOfYear(), $today->endOfDay(), 'this_year', 'Este año');
        }

        if (Str::contains($lower, ['este mes', 'del mes', 'mensual'])) {
            return new ReportDateRange($today->startOfMonth(), $today->endOfDay(), 'this_month', 'Este mes');
        }

        foreach (self::SPANISH_MONTHS as $name => $monthNumber) {
            if (Str::contains($lower, $name)) {
                // No year was said — assume the most recent occurrence of
                // that month (this year if it already happened, last year
                // otherwise), never a future date.
                $year = $today->month >= $monthNumber ? $today->year : $today->year - 1;
                $start = CarbonImmutable::create($year, $monthNumber, 1)->startOfDay();
                $end = $start->endOfMonth();
                $end = $end->greaterThan($today->endOfDay()) ? $today->endOfDay() : $end;

                return new ReportDateRange($start, $end, 'custom', ucfirst($name).' '.$year);
            }
        }

        return null;
    }
}
