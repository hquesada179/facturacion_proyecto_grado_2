<?php

namespace Tests\Unit\Invoices;

use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\Tax;
use App\Services\Invoices\Calculation\InvoiceCalculationResult;
use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\Calculation\InvoiceLineResult;
use App\Services\Invoices\Calculation\InvoiceLineTaxInput;
use App\Services\Invoices\Calculation\InvoiceLineTaxResult;
use App\Services\Invoices\Validation\InvoiceLineContext;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationEngine;
use App\Services\Invoices\Validation\ValidationResultCollection;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Entirely DB-free: Eloquent models are instantiated (never saved), which
 * is enough for attribute casts (enums, booleans, dates) to apply.
 */
class ValidationEngineTest extends TestCase
{
    private ValidationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = ValidationEngine::default();
    }

    private function activeCustomer(array $overrides = []): Customer
    {
        return new Customer(array_merge([
            'identification_type' => 'CC',
            'identification_number' => '1020304050',
            'name' => 'Cliente de Prueba',
            'email' => 'cliente@example.com',
            'status' => 'active',
            'is_final_consumer' => false,
        ], $overrides));
    }

    private function activeTax(array $overrides = []): Tax
    {
        $tax = new Tax(array_merge([
            'code' => 'IVA19',
            'name' => 'IVA 19%',
            'rate' => '19',
            'is_active' => true,
            'valid_from' => null,
            'valid_until' => null,
        ], $overrides));
        $tax->id = $overrides['id'] ?? 1;

        return $tax;
    }

    private function activeProduct(array $overrides = []): ProductService
    {
        $product = new ProductService(array_merge([
            'sku' => 'PS-1',
            'name' => 'Producto de prueba',
            'unit' => 'unidad',
            'price' => '100.00',
            'status' => ProductStatus::Active->value,
        ], $overrides));
        $product->id = $overrides['id'] ?? 1;

        return $product;
    }

    /**
     * @param array<int, array{
     *     product?: ?ProductService,
     *     productReferenced?: bool,
     *     description?: string,
     *     unit?: string,
     *     quantity?: string,
     *     unitPrice?: string,
     *     discountPercent?: string,
     *     taxes?: Tax[],
     * }> $lines
     */
    private function buildContext(
        ?Customer $customer,
        array $lines,
        ?string $paymentType = null,
        ?Carbon $issueDate = null,
        ?Carbon $dueDate = null,
        ?InvoiceCalculationResult $calculationOverride = null,
    ): ValidationContext {
        $lineInputs = [];
        foreach ($lines as $line) {
            $taxInputs = array_map(
                static fn (Tax $tax): InvoiceLineTaxInput => new InvoiceLineTaxInput(
                    taxId: $tax->id,
                    code: $tax->code,
                    name: $tax->name,
                    rate: (string) $tax->rate,
                ),
                $line['taxes'] ?? []
            );

            $lineInputs[] = new InvoiceLineInput(
                productServiceId: ($line['product'] ?? null)?->id,
                description: $line['description'] ?? 'Línea',
                unit: $line['unit'] ?? 'unidad',
                quantity: $line['quantity'] ?? '1',
                unitPrice: $line['unitPrice'] ?? '100',
                discountPercent: $line['discountPercent'] ?? '0',
                taxes: $taxInputs,
            );
        }

        $calculation = (new InvoiceCalculator)->calculate(new InvoiceDraftInput(lines: $lineInputs));

        $lineContexts = [];
        foreach ($calculation->lines as $index => $calculatedLine) {
            $line = $lines[$index];
            $lineContexts[] = new InvoiceLineContext(
                index: $index,
                productServiceId: ($line['product'] ?? null)?->id,
                productReferenced: $line['productReferenced'] ?? (($line['product'] ?? null) !== null),
                product: $line['product'] ?? null,
                description: $line['description'] ?? 'Línea',
                unit: $line['unit'] ?? 'unidad',
                quantity: $line['quantity'] ?? '1',
                unitPrice: $line['unitPrice'] ?? '100',
                discountPercent: $line['discountPercent'] ?? '0',
                taxes: $line['taxes'] ?? [],
                calculated: $calculatedLine,
            );
        }

        return new ValidationContext(
            company: new Company(['name' => 'Empresa de prueba']),
            customer: $customer,
            lines: $lineContexts,
            calculation: $calculationOverride ?? $calculation,
            paymentType: $paymentType,
            issueDate: $issueDate,
            dueDate: $dueDate,
        );
    }

    private function codes(ValidationResultCollection $results): array
    {
        return array_map(static fn ($result) => $result->codigo, $results->all());
    }

    public function test_valid_invoice_has_no_blocking_results(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'taxes' => [$this->activeTax()]]],
        );

        $results = $this->engine->run($context);

        $this->assertFalse($results->hasBlocking());
    }

    public function test_nonexistent_customer_blocks(): void
    {
        $context = $this->buildContext(null, [['product' => $this->activeProduct()]]);

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-CUSTOMER-001', $this->codes($results));
    }

    public function test_inactive_customer_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(['status' => 'inactive']),
            [['product' => $this->activeProduct()]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-CUSTOMER-002', $this->codes($results));
    }

    public function test_invalid_identification_type_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(['identification_type' => 'XX']),
            [['product' => $this->activeProduct()]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-CUSTOMER-003', $this->codes($results));
    }

    public function test_incorrect_dv_blocks(): void
    {
        // dv is intentionally not mass-assignable (see Customer model), so
        // it must be set directly, same as production code does.
        $customer = $this->activeCustomer(['identification_type' => 'NIT', 'identification_number' => '900373115']);
        $customer->dv = '9';

        $context = $this->buildContext($customer, [['product' => $this->activeProduct()]]);

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-CUSTOMER-006', $this->codes($results));
    }

    public function test_correct_dv_does_not_block(): void
    {
        $customer = $this->activeCustomer(['identification_type' => 'NIT', 'identification_number' => '900373115']);
        $customer->dv = '3';

        $context = $this->buildContext($customer, [['product' => $this->activeProduct()]]);

        $results = $this->engine->run($context);

        $this->assertNotContains('PRO-CUSTOMER-006', $this->codes($results));
    }

    public function test_misconfigured_final_consumer_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(['is_final_consumer' => true, 'identification_number' => '999']),
            [['product' => $this->activeProduct()]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-CUSTOMER-005', $this->codes($results));
    }

    public function test_invoice_without_lines_blocks(): void
    {
        $context = $this->buildContext($this->activeCustomer(), []);

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-ITEMS-001', $this->codes($results));
    }

    public function test_inactive_product_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(['status' => ProductStatus::Inactive->value])]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-ITEMS-003', $this->codes($results));
    }

    public function test_discontinued_product_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(['status' => ProductStatus::Discontinued->value])]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-ITEMS-004', $this->codes($results));
    }

    public function test_quantity_zero_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'quantity' => '0']],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-QTY-001', $this->codes($results));
    }

    public function test_negative_quantity_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'quantity' => '-1']],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-QTY-001', $this->codes($results));
    }

    public function test_negative_price_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'unitPrice' => '-100']],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-PRICE-001', $this->codes($results));
    }

    public function test_discount_over_one_hundred_percent_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'discountPercent' => '150']],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-DISCOUNT-001', $this->codes($results));
        $this->assertContains('PRO-DISCOUNT-002', $this->codes($results));
    }

    public function test_inactive_tax_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'taxes' => [$this->activeTax(['is_active' => false])]]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-TAX-001', $this->codes($results));
    }

    public function test_expired_tax_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'taxes' => [
                $this->activeTax(['valid_until' => Carbon::yesterday()]),
            ]]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-TAX-002', $this->codes($results));
    }

    public function test_duplicated_tax_blocks(): void
    {
        $tax = $this->activeTax();
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'taxes' => [$tax, $tax]]],
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-TAX-003', $this->codes($results));
    }

    public function test_tax_base_inconsistency_blocks(): void
    {
        $context = $this->buildContext($this->activeCustomer(), [['product' => $this->activeProduct()]]);

        // Replace the first line's calculated result with a hand-crafted,
        // internally inconsistent one (value does not equal base × rate / 100).
        $badTaxResult = new InvoiceLineTaxResult(taxId: 1, code: 'IVA19', name: 'IVA 19%', base: '100.00', rate: '19', value: '50.00');
        $badLineResult = new InvoiceLineResult(
            index: 0,
            description: 'Línea',
            grossAmount: '100.00',
            discountAmount: '0.00',
            chargesAmount: '0.00',
            taxableBase: '100.00',
            taxes: [$badTaxResult],
            taxTotal: '50.00',
            lineTotal: '150.00',
        );

        $tamperedLine = new InvoiceLineContext(
            index: 0,
            productServiceId: 1,
            productReferenced: true,
            product: $this->activeProduct(),
            description: 'Línea',
            unit: 'unidad',
            quantity: '1',
            unitPrice: '100',
            discountPercent: '0',
            taxes: [],
            calculated: $badLineResult,
        );

        $context = new ValidationContext(
            company: $context->company,
            customer: $context->customer,
            lines: [$tamperedLine],
            calculation: $context->calculation,
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-TAX-004', $this->codes($results));
    }

    public function test_totals_inconsistency_blocks(): void
    {
        $context = $this->buildContext($this->activeCustomer(), [['product' => $this->activeProduct()]]);

        $tamperedCalculation = new InvoiceCalculationResult(
            lines: $context->calculation->lines,
            subtotal: '999.00',
            totalDiscounts: $context->calculation->totalDiscounts,
            totalCharges: $context->calculation->totalCharges,
            globalCharges: $context->calculation->globalCharges,
            taxesByCode: $context->calculation->taxesByCode,
            totalTaxes: $context->calculation->totalTaxes,
            total: $context->calculation->total,
        );

        $context = new ValidationContext(
            company: $context->company,
            customer: $context->customer,
            lines: $context->lines,
            calculation: $tamperedCalculation,
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-TOTALS-001', $this->codes($results));
    }

    public function test_warning_does_not_block(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(), 'unitPrice' => '0']],
        );

        $results = $this->engine->run($context);

        $this->assertFalse($results->hasBlocking());
        $this->assertGreaterThan(0, $results->warningCount());
        $this->assertContains('PRO-PRICE-002', $this->codes($results));
    }

    public function test_catalog_price_deviation_is_a_warning_not_a_block(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct(['price' => '100.00']), 'unitPrice' => '1000']],
        );

        $results = $this->engine->run($context);

        $this->assertFalse($results->hasBlocking());
        $this->assertContains('PRO-PRICE-003', $this->codes($results));
    }

    public function test_malformed_email_is_a_warning_not_a_block(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(['email' => 'not-an-email']),
            [['product' => $this->activeProduct()]],
        );

        $results = $this->engine->run($context);

        $this->assertFalse($results->hasBlocking());
        $this->assertContains('PRO-CUSTOMER-007', $this->codes($results));
    }

    public function test_credit_payment_without_due_date_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct()]],
            paymentType: 'credito',
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-PAYMENT-001', $this->codes($results));
    }

    public function test_due_date_before_issue_date_blocks(): void
    {
        $context = $this->buildContext(
            $this->activeCustomer(),
            [['product' => $this->activeProduct()]],
            paymentType: 'credito',
            issueDate: Carbon::parse('2026-01-15'),
            dueDate: Carbon::parse('2026-01-01'),
        );

        $results = $this->engine->run($context);

        $this->assertTrue($results->hasBlocking());
        $this->assertContains('PRO-PAYMENT-002', $this->codes($results));
    }

    public function test_dormant_payment_rules_produce_nothing_when_payment_data_is_absent(): void
    {
        $context = $this->buildContext($this->activeCustomer(), [['product' => $this->activeProduct()]]);

        $results = $this->engine->run($context);

        $this->assertNotContains('PRO-PAYMENT-001', $this->codes($results));
        $this->assertNotContains('PRO-PAYMENT-002', $this->codes($results));
    }

    public function test_json_result_preserves_required_fields(): void
    {
        $context = $this->buildContext(null, [['product' => $this->activeProduct()]]);

        $results = $this->engine->run($context);
        $first = $results->toArray()['results'][0];

        $this->assertArrayHasKey('codigo', $first);
        $this->assertArrayHasKey('regla', $first);
        $this->assertArrayHasKey('severidad', $first);
        $this->assertArrayHasKey('campo', $first);
        $this->assertArrayHasKey('mensaje', $first);
        $this->assertArrayHasKey('sugerencia', $first);
    }
}
