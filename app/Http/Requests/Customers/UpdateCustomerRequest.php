<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('identification_number')) {
            $this->merge([
                'identification_number' => Customer::normalizeIdentificationNumber((string) $this->input('identification_number')),
            ]);
        }
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
                Rule::unique('customers')
                    ->where('company_id', $this->user()?->company_id)
                    ->ignore($this->route('customer')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array($this->input('identification_type'), Customer::NUMERIC_IDENTIFICATION_TYPES, true) && ! ctype_digit((string) $value)) {
                        $fail('Para este tipo de identificación, el número solo puede contener dígitos.');
                    }
                },
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
