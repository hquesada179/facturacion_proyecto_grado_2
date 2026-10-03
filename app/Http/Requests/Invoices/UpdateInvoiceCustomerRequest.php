<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
        ];
    }
}
