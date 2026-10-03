<?php

namespace Tests\Unit\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\Invoices\CufeGenerator;
use Tests\TestCase;

class CufeGeneratorTest extends TestCase
{
    private function invoice(array $overrides = []): Invoice
    {
        $company = new Company(['nit' => '900373115']);

        $invoice = new Invoice(array_merge([
            'customer_id' => 1,
            'issue_date' => '2026-10-03',
        ], $overrides));
        $invoice->number = $overrides['number'] ?? 'FV-000001';
        $invoice->total = $overrides['total'] ?? '119000.00';
        $invoice->setRelation('company', $company);

        return $invoice;
    }

    public function test_cufe_has_the_simulated_prefix(): void
    {
        $cufe = (new CufeGenerator)->generate($this->invoice());

        $this->assertStringStartsWith('SIM-', $cufe);
    }

    public function test_cufe_is_a_sha384_hash(): void
    {
        $cufe = (new CufeGenerator)->generate($this->invoice());
        $hash = substr($cufe, 4);

        // SHA-384 produces a 96-character hex digest.
        $this->assertSame(96, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $hash);
    }

    public function test_generation_is_deterministic_for_the_same_invoice_data(): void
    {
        $generator = new CufeGenerator;

        $first = $generator->generate($this->invoice());
        $second = $generator->generate($this->invoice());

        $this->assertSame($first, $second);
    }

    public function test_different_invoice_numbers_produce_different_cufes(): void
    {
        $generator = new CufeGenerator;

        $first = $generator->generate($this->invoice(['number' => 'FV-000001']));
        $second = $generator->generate($this->invoice(['number' => 'FV-000002']));

        $this->assertNotSame($first, $second);
    }

    public function test_different_totals_produce_different_cufes(): void
    {
        $generator = new CufeGenerator;

        $first = $generator->generate($this->invoice(['total' => '100.00']));
        $second = $generator->generate($this->invoice(['total' => '200.00']));

        $this->assertNotSame($first, $second);
    }
}
