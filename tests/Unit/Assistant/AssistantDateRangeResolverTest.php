<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\AssistantDateRangeResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AssistantDateRangeResolverTest extends TestCase
{
    private function resolver(): AssistantDateRangeResolver
    {
        return new AssistantDateRangeResolver;
    }

    public function test_hoy_resolves_to_todays_start_and_end(): void
    {
        $range = $this->resolver()->resolve('¿cuántas facturas emití hoy?');
        $today = CarbonImmutable::now()->startOfDay();

        $this->assertNotNull($range);
        $this->assertTrue($range->start->isSameDay($today));
        $this->assertTrue($range->end->isSameDay($today));
    }

    public function test_este_mes_resolves_to_the_current_calendar_month(): void
    {
        $range = $this->resolver()->resolve('¿cuánto he facturado este mes?');
        $today = CarbonImmutable::now();

        $this->assertNotNull($range);
        $this->assertTrue($range->start->isSameDay($today->startOfMonth()));
        $this->assertSame($today->month, $range->start->month);
    }

    public function test_ultimos_30_dias_resolves_to_a_thirty_day_window(): void
    {
        $range = $this->resolver()->resolve('iva generado en los últimos 30 días');

        $this->assertNotNull($range);
        $this->assertEquals(29, $range->start->diffInDays($range->end->startOfDay()));
    }

    public function test_mes_pasado_resolves_to_the_previous_calendar_month(): void
    {
        $range = $this->resolver()->resolve('facturas emitidas el mes pasado');
        $expectedMonth = CarbonImmutable::now()->subMonthNoOverflow()->month;

        $this->assertNotNull($range);
        $this->assertSame($expectedMonth, $range->start->month);
        $this->assertSame($expectedMonth, $range->end->month);
    }

    public function test_ayer_resolves_to_yesterday_only(): void
    {
        $range = $this->resolver()->resolve('facturas de ayer');
        $yesterday = CarbonImmutable::now()->subDay();

        $this->assertNotNull($range);
        $this->assertTrue($range->start->isSameDay($yesterday));
        $this->assertTrue($range->end->isSameDay($yesterday));
    }

    public function test_returns_null_when_no_date_phrase_is_present(): void
    {
        $this->assertNull($this->resolver()->resolve('muéstrame las facturas de kata'));
    }

    public function test_never_produces_a_future_end_date(): void
    {
        $range = $this->resolver()->resolve('facturas del mes pasado');

        $this->assertNotNull($range);
        $this->assertTrue($range->end->lessThanOrEqualTo(CarbonImmutable::now()->endOfDay()));
    }
}
