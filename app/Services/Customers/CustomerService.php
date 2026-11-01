<?php

namespace App\Services\Customers;

use App\Models\Company;
use App\Models\Customer;
use App\Services\Tax\NitDvCalculator;

class CustomerService
{
    /**
     * Shared by the standalone customer form and the invoice wizard's
     * "quick create" step, so the NIT check-digit rule can never drift
     * between the two entry points.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromData(array $data): Customer
    {
        $customer = new Customer($data);
        $customer->dv = $this->calculateDv($data);
        $customer->save();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFromData(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->dv = $this->calculateDv($data);
        $customer->save();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calculateDv(array $data): ?string
    {
        return ($data['identification_type'] ?? null) === 'NIT'
            ? (string) NitDvCalculator::calculate($data['identification_number'])
            : null;
    }

    /**
     * The identification number used for the prototype's "Consumidor
     * final" placeholder customer. Purely fictitious — not a real DIAN
     * identifier — and only unique enough to not collide with real test
     * data within a single company.
     */
    public const FINAL_CONSUMER_IDENTIFICATION = '0000000000';

    /**
     * Idempotent: safe to call every time a company is provisioned.
     */
    public function ensureFinalConsumer(Company $company): Customer
    {
        $existing = Customer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_final_consumer', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = new Customer([
            'person_type' => 'natural',
            'identification_type' => 'CC',
            'identification_number' => self::FINAL_CONSUMER_IDENTIFICATION,
            'name' => 'Consumidor Final',
            'status' => 'active',
            'is_final_consumer' => true,
            'country' => 'Colombia',
        ]);
        $customer->company_id = $company->id;
        $customer->save();

        return $customer;
    }
}
