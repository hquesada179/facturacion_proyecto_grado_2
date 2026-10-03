<?php

namespace Tests\Unit\Invoices;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Invoices\Calculation\InvoiceCalculationResult;
use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\DianSimulator;
use App\Services\Invoices\Validation\InvoiceLineContext;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationEngine;
use InvalidArgumentException;
use Tests\TestCase;

class DianSimulatorTest extends TestCase
{
    private function context(?Customer $customer, bool $withValidLine = false): ValidationContext
    {
        if (! $withValidLine) {
            $calculation = new InvoiceCalculationResult(
                lines: [],
                subtotal: '0.00',
                totalDiscounts: '0.00',
                totalCharges: '0.00',
                globalCharges: '0.00',
                taxesByCode: [],
                totalTaxes: '0.00',
                total: '0.00',
            );

            return new ValidationContext(
                company: new Company,
                customer: $customer,
                lines: [],
                calculation: $calculation,
            );
        }

        $calculation = (new InvoiceCalculator)->calculate(new InvoiceDraftInput(lines: [
            new InvoiceLineInput(productServiceId: null, description: 'Línea', unit: 'unidad', quantity: '1', unitPrice: '100'),
        ]));

        $lineContext = new InvoiceLineContext(
            index: 0,
            productServiceId: null,
            productReferenced: false,
            product: null,
            description: 'Línea',
            unit: 'unidad',
            quantity: '1',
            unitPrice: '100',
            discountPercent: '0',
            taxes: [],
            calculated: $calculation->lines[0],
        );

        return new ValidationContext(
            company: new Company,
            customer: $customer,
            lines: [$lineContext],
            calculation: $calculation,
        );
    }

    public function test_forced_validada_scenario(): void
    {
        $simulator = new DianSimulator(ValidationEngine::default());
        $result = $simulator->simulate(new Invoice, $this->context(new Customer(['status' => 'active'])), 'validada');

        $this->assertSame('validada', $result->status);
        $this->assertStringContainsString('Documento de prueba', $result->message);
        $this->assertStringContainsString('sin validez tributaria', $result->message);
    }

    public function test_forced_rechazada_scenario(): void
    {
        $simulator = new DianSimulator(ValidationEngine::default());
        $result = $simulator->simulate(new Invoice, $this->context(null), 'rechazada');

        $this->assertSame('rechazada', $result->status);
        $this->assertStringContainsString('rechazada', $result->message);
    }

    public function test_forced_error_scenario(): void
    {
        $simulator = new DianSimulator(ValidationEngine::default());
        $result = $simulator->simulate(new Invoice, $this->context(null), 'error');

        $this->assertSame('error', $result->status);
        $this->assertStringContainsString('Error técnico', $result->message);
    }

    public function test_unknown_forced_scenario_is_rejected(): void
    {
        $simulator = new DianSimulator(ValidationEngine::default());

        $this->expectException(InvalidArgumentException::class);
        $simulator->simulate(new Invoice, $this->context(null), 'aprobada-en-el-cielo');
    }

    public function test_automatic_mode_approves_when_there_is_no_blocking_issue(): void
    {
        $simulator = new DianSimulator(ValidationEngine::default());

        $customer = new Customer([
            'identification_type' => 'CC',
            'identification_number' => '1020304050',
            'name' => 'Cliente de prueba',
            'status' => 'active',
        ]);

        $result = $simulator->simulate(new Invoice, $this->context($customer, withValidLine: true));

        $this->assertSame('validada', $result->status);
    }

    public function test_automatic_mode_rejects_when_there_is_a_blocking_issue(): void
    {
        $simulator = new DianSimulator(ValidationEngine::default());

        // No customer at all → CustomerExistsRule blocks.
        $result = $simulator->simulate(new Invoice, $this->context(null));

        $this->assertSame('rechazada', $result->status);
    }
}
