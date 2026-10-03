<?php

namespace App\Http\Requests\Settings;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-company');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'person_type' => ['required', Rule::in(['natural', 'juridica'])],
            'nit' => ['required', 'string', 'regex:/^[0-9]{5,15}$/'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'tax_regime' => ['nullable', 'string', 'max:120'],
            'fiscal_responsibilities' => ['nullable', 'array'],
            'fiscal_responsibilities.*' => [Rule::in(array_keys(Company::FISCAL_RESPONSIBILITIES))],
            'main_tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'currency' => ['required', Rule::in(Company::CURRENCIES)],
            'is_test_environment' => ['nullable', 'boolean'],
            'invoice_prefix' => ['nullable', 'string', 'max:10'],
        ];
    }
}
