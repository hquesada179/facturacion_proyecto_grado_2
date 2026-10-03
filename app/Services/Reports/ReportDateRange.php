<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

class ReportDateRange
{
    public const DEFAULT = 'last_30_days';

    public const OPTIONS = [
        'today' => 'Hoy',
        'last_7_days' => 'Últimos 7 días',
        'this_month' => 'Este mes',
        'last_30_days' => 'Últimos 30 días',
        'custom' => 'Personalizado',
    ];

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $key,
        public readonly string $label,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilters(array $filters): self
    {
        $key = isset($filters['date_range']) && array_key_exists($filters['date_range'], self::OPTIONS)
            ? (string) $filters['date_range']
            : self::DEFAULT;

        $today = CarbonImmutable::now()->startOfDay();

        if ($key === 'custom') {
            $start = self::parseDate($filters['start_date'] ?? null)?->startOfDay();
            $end = self::parseDate($filters['end_date'] ?? null)?->endOfDay();

            if ($start && $end) {
                if ($start->greaterThan($end)) {
                    [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
                }

                return new self($start, $end, $key, $start->format('d/m/Y').' - '.$end->format('d/m/Y'));
            }

            $key = self::DEFAULT;
        }

        return match ($key) {
            'today' => new self($today, $today->endOfDay(), $key, self::OPTIONS[$key]),
            'last_7_days' => new self($today->subDays(6), $today->endOfDay(), $key, self::OPTIONS[$key]),
            'this_month' => new self($today->startOfMonth(), $today->endOfDay(), $key, self::OPTIONS[$key]),
            default => new self($today->subDays(29), $today->endOfDay(), self::DEFAULT, self::OPTIONS[self::DEFAULT]),
        };
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
