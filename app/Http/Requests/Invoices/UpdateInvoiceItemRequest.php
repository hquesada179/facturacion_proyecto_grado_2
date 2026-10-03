<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    public function rules(): array
    {
        return [
            'product_service_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'quantity' => ['required', 'numeric'],
            'unit_price' => ['required', 'numeric'],
            'discount_percent' => ['nullable', 'numeric'],
            'tax_ids' => ['nullable', 'array'],
            'tax_ids.*' => ['integer', 'exists:taxes,id'],
        ];
    }
}
