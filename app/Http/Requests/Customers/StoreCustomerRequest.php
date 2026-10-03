<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    public function rules(): array
    {
        return [
            'person_type' => ['required', Rule::in(['natural', 'juridica'])],
            'identification_type' => ['required', Rule::in(array_keys(Customer::IDENTIFICATION_TYPES))],
            'identification_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers')->where('company_id', $this->user()?->company_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', Rule::requiredIf($this->input('person_type') === 'juridica')],
            'address' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('person_type') === 'juridica')],
            'city' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'tax_responsibility' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
        ];
    }
}
