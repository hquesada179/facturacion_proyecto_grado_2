<?php

namespace App\Support;

class ReportFormatter
{
    public static function integer(int|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, ',', '.');
    }

    public static function money(int|float|string|null $value): string
    {
        return '$ '.number_format((float) ($value ?? 0), 2, ',', '.');
    }

    public static function percent(int|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 1, ',', '.').'%';
    }

    public static function duration(?float $seconds): string
    {
        if ($seconds === null) {
            return 'Sin datos';
        }

        $seconds = max(0, (int) round($seconds));

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);
            $remainingSeconds = $seconds % 60;

            return $remainingSeconds > 0
                ? "{$minutes} min {$remainingSeconds} s"
                : "{$minutes} min";
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $minutes > 0
            ? "{$hours} h {$minutes} min"
            : "{$hours} h";
    }
}
