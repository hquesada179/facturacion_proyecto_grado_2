<?php

namespace Tests\Unit\Invoices;

use App\Models\Tax;
use App\Services\Invoices\Calculation\TaxInclusivePriceResolver;
use Tests\TestCase;

class TaxInclusivePriceResolverTest extends TestCase
{
    private function tax(string $rate): Tax
    {
        return new Tax(['rate' => $rate]);
    }

    public function test_extracts_the_exact_base_for_a_single_tax(): void
    {
        $resolver = new TaxInclusivePriceResolver;

        $base = $resolver->extractBase('119000', [$this->tax('19')]);

        $this->assertSame('100000.00', $base);
    }

    public function test_extracts_the_base_across_multiple_combined_taxes(): void
    {
        $resolver = new TaxInclusivePriceResolver;

        // 100000 base + 19% + 8% = 127000 gross.
        $base = $resolver->extractBase('127000', [$this->tax('19'), $this->tax('8')]);

        $this->assertSame('100000.00', $base);
    }

    public function test_returns_the_same_price_when_there_are_no_taxes(): void
    {
        $resolver = new TaxInclusivePriceResolver;

        $base = $resolver->extractBase('100000', []);

        $this->assertSame('100000.00', $base);
    }

    public function test_rounds_to_two_decimals_when_division_is_not_exact(): void
    {
        $resolver = new TaxInclusivePriceResolver;

        $base = $resolver->extractBase('100', [$this->tax('19')]);

        $this->assertSame('84.03', $base);
    }
}
