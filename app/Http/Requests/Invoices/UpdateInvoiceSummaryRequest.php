<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_type' => ['nullable', Rule::in(['contado', 'credito'])],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
