<?php

namespace Tests\Unit\Invoices;

use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\Calculation\InvoiceLineTaxInput;
use Tests\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    private InvoiceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new InvoiceCalculator;
    }

    private function line(
        string $quantity,
        string $unitPrice,
        string $discountPercent = '0',
        string $charges = '0',
        array $taxes = [],
    ): InvoiceLineInput {
        return new InvoiceLineInput(
            productServiceId: null,
            description: 'Línea de prueba',
            unit: 'unidad',
            quantity: $quantity,
            unitPrice: $unitPrice,
            discountPercent: $discountPercent,
            charges: $charges,
            taxes: $taxes,
        );
    }

    private function tax(string $code, string $rate, ?int $id = 1): InvoiceLineTaxInput
    {
        return new InvoiceLineTaxInput(taxId: $id, code: $code, name: $code, rate: $rate);
    }

    public function test_single_line_with_iva_19_percent(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('100000.00', $result->subtotal);
        $this->assertSame('19000.00', $result->totalTaxes);
        $this->assertSame('119000.00', $result->total);
    }

    public function test_single_line_with_iva_5_percent(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', taxes: [$this->tax('IVA5', '5')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('5000.00', $result->totalTaxes);
        $this->assertSame('105000.00', $result->total);
    }

    public function test_iva_zero_percent(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '50000', taxes: [$this->tax('IVA0', '0')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.00', $result->totalTaxes);
        $this->assertSame('50000.00', $result->total);
        $this->assertSame('IVA0', $result->lines[0]->taxes[0]->code);
    }

    public function test_exento(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '50000', taxes: [$this->tax('EXENTO', '0')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.00', $result->totalTaxes);
        // Distinguishable from IVA0 / EXCLUIDO even though the math is identical.
        $this->assertSame('EXENTO', $result->lines[0]->taxes[0]->code);
    }

    public function test_excluido(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '50000', taxes: [$this->tax('EXCLUIDO', '0')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.00', $result->totalTaxes);
        $this->assertSame('EXCLUIDO', $result->lines[0]->taxes[0]->code);
        $this->assertCount(1, $result->taxesByCode);
        $this->assertArrayHasKey('EXCLUIDO', $result->taxesByCode);
    }

    public function test_inc_8_percent(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('2', '50000', taxes: [$this->tax('INC8', '8')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('100000.00', $result->subtotal);
        $this->assertSame('8000.00', $result->totalTaxes);
        $this->assertSame('108000.00', $result->total);
    }

    public function test_iva_and_inc_combined_on_same_line(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', taxes: [
                $this->tax('IVA19', '19', 1),
                $this->tax('INC8', '8', 2),
            ]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertCount(2, $result->lines[0]->taxes);
        $this->assertSame('27000.00', $result->lines[0]->taxTotal);
        $this->assertSame('127000.00', $result->lines[0]->lineTotal);
        $this->assertSame('27000.00', $result->totalTaxes);
        $this->assertArrayHasKey('IVA19', $result->taxesByCode);
        $this->assertArrayHasKey('INC8', $result->taxesByCode);
    }

    public function test_decimal_quantity(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('2.5', '1000', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('2500.00', $result->subtotal);
        $this->assertSame('475.00', $result->totalTaxes);
        $this->assertSame('2975.00', $result->total);
    }

    public function test_percentage_discount(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', discountPercent: '10', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('10000.00', $result->totalDiscounts);
        $this->assertSame('90000.00', $result->subtotal);
        $this->assertSame('17100.00', $result->totalTaxes);
        $this->assertSame('107100.00', $result->total);
    }

    public function test_discount_of_one_hundred_percent_zeroes_the_line(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', discountPercent: '100', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.00', $result->subtotal);
        $this->assertSame('0.00', $result->totalTaxes);
        $this->assertSame('0.00', $result->total);
    }

    public function test_discount_percent_over_one_hundred_is_clamped_to_gross(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', discountPercent: '150'),
        ]);

        $result = $this->calculator->calculate($draft);

        // The calculator is pure math and defensively clamps; ValidationEngine
        // is responsible for flagging an out-of-range percent as a blocker.
        $this->assertSame('100000.00', $result->totalDiscounts);
        $this->assertSame('0.00', $result->subtotal);
    }

    public function test_several_lines_sum_correctly(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100000', taxes: [$this->tax('IVA19', '19')]),
            $this->line('1', '100000', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('200000.00', $result->subtotal);
        $this->assertSame('38000.00', $result->totalTaxes);
        $this->assertSame('238000.00', $result->total);
        $this->assertSame('200000.00', $result->taxesByCode['IVA19']['base']);
        $this->assertSame('38000.00', $result->taxesByCode['IVA19']['value']);
    }

    public function test_half_even_rounding_rounds_down_to_even_neighbour(): void
    {
        // 12.50 * 1% = 0.1250 exactly — a genuine tie between 0.12 and 0.13.
        // HALF_EVEN picks 0.12 because 2 is even.
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '12.50', taxes: [$this->tax('TEST1', '1')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.12', $result->lines[0]->taxTotal);
    }

    public function test_half_even_rounding_rounds_up_to_even_neighbour(): void
    {
        // 13.50 * 1% = 0.1350 exactly — a tie between 0.13 and 0.14.
        // HALF_EVEN picks 0.14 because 4 is even.
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '13.50', taxes: [$this->tax('TEST1', '1')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.14', $result->lines[0]->taxTotal);
    }

    public function test_small_values_round_to_zero_when_below_half_a_cent(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '0.01', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.00', $result->lines[0]->taxTotal);
        $this->assertSame('0.01', $result->lines[0]->lineTotal);
    }

    public function test_large_but_reasonable_values(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1000', '999999.99', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('999999990.00', $result->subtotal);
        $this->assertSame('189999998.10', $result->totalTaxes);
        $this->assertSame('1189999988.10', $result->total);
    }

    public function test_calculation_is_deterministic(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('3', '33333.33', discountPercent: '7.5', taxes: [
                $this->tax('IVA19', '19'),
                $this->tax('INC8', '8'),
            ]),
        ]);

        $first = $this->calculator->calculate($draft)->toArray();
        $second = $this->calculator->calculate($draft)->toArray();

        $this->assertSame($first, $second);
    }

    public function test_no_floating_point_errors_across_lines(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('0.1', '1'),
            $this->line('0.2', '1'),
        ]);

        $result = $this->calculator->calculate($draft);

        // With float arithmetic 0.1 + 0.2 famously yields 0.30000000000000004.
        $this->assertSame('0.30', $result->subtotal);
    }

    public function test_zero_quantity_line_computes_to_zero_without_error(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('0', '100000', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('0.00', $result->subtotal);
        $this->assertSame('0.00', $result->total);
    }

    public function test_negative_price_is_computed_without_validation(): void
    {
        // The calculator is pure math — rejecting a negative price is
        // ValidationEngine's job, not InvoiceCalculator's.
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '-100', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('-100.00', $result->subtotal);
        $this->assertSame('-19.00', $result->totalTaxes);
        $this->assertSame('-119.00', $result->total);
    }

    public function test_per_line_charges_are_added_to_the_taxable_base(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100', charges: '10', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('110.00', $result->lines[0]->taxableBase);
        $this->assertSame('20.90', $result->lines[0]->taxTotal);
        $this->assertSame('130.90', $result->lines[0]->lineTotal);
    }

    public function test_global_charges_are_added_once_at_invoice_level(): void
    {
        $draft = new InvoiceDraftInput(
            lines: [
                $this->line('1', '100'),
                $this->line('1', '100'),
            ],
            globalCharges: '50',
        );

        $result = $this->calculator->calculate($draft);

        $this->assertSame('200.00', $result->subtotal);
        $this->assertSame('50.00', $result->globalCharges);
        $this->assertSame('250.00', $result->total);
    }

    public function test_same_tax_code_across_lines_is_aggregated(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100', taxes: [$this->tax('IVA19', '19')]),
            $this->line('1', '100', taxes: [$this->tax('IVA19', '19')]),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame('200.00', $result->taxesByCode['IVA19']['base']);
        $this->assertSame('38.00', $result->taxesByCode['IVA19']['value']);
    }

    public function test_empty_draft_returns_zeroed_totals(): void
    {
        $result = $this->calculator->calculate(new InvoiceDraftInput);

        $this->assertSame([], $result->lines);
        $this->assertSame('0.00', $result->subtotal);
        $this->assertSame('0.00', $result->totalTaxes);
        $this->assertSame('0.00', $result->total);
    }

    public function test_line_without_taxes_has_zero_tax_total(): void
    {
        $draft = new InvoiceDraftInput(lines: [
            $this->line('1', '100'),
        ]);

        $result = $this->calculator->calculate($draft);

        $this->assertSame([], $result->lines[0]->taxes);
        $this->assertSame('0.00', $result->lines[0]->taxTotal);
        $this->assertSame('100.00', $result->lines[0]->lineTotal);
    }
}
