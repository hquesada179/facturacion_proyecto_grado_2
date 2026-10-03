<?php

namespace Tests\Unit;

use App\Services\Tax\NitDvCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class NitDvCalculatorTest extends TestCase
{
    public function test_calculates_known_check_digits(): void
    {
        $this->assertSame(3, NitDvCalculator::calculate('900373115'));
        $this->assertSame(4, NitDvCalculator::calculate('900000111'));
    }

    public function test_handles_remainder_zero_and_one_edge_cases(): void
    {
        $this->assertSame(0, NitDvCalculator::calculate('0'));
        $this->assertSame(1, NitDvCalculator::calculate('4'));
    }

    public function test_strips_punctuation_before_calculating(): void
    {
        $this->assertSame(3, NitDvCalculator::calculate('900.373.115'));
        $this->assertSame(3, NitDvCalculator::calculate('900-373-115'));
    }

    public function test_is_valid_matches_the_calculated_digit(): void
    {
        $this->assertTrue(NitDvCalculator::isValid('900373115', 3));
        $this->assertTrue(NitDvCalculator::isValid('900373115', '3'));
        $this->assertFalse(NitDvCalculator::isValid('900373115', 5));
    }

    public function test_rejects_input_without_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NitDvCalculator::calculate('abc');
    }

    public function test_rejects_nit_longer_than_fifteen_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NitDvCalculator::calculate('1234567890123456');
    }
}
