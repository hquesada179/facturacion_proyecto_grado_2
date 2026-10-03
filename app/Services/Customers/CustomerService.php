<?php

namespace App\Services\Customers;

use App\Models\Company;
use App\Models\Customer;

class CustomerService
{
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
