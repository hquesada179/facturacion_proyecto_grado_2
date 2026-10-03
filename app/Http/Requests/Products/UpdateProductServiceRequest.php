<?php

namespace App\Http\Requests\Products;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product_services')
                    ->where('company_id', $this->user()?->company_id)
                    ->ignore($this->route('product')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['product', 'service'])],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()))],
            'tax_included' => ['nullable', 'boolean'],
            'taxes' => ['nullable', 'array'],
            'taxes.*' => ['integer', 'exists:taxes,id'],
        ];
    }
}
