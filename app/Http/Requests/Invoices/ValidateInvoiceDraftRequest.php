<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates only the *shape* of the draft, never business rules and
 * never subtotal/tax_total/total/status — those are never read from the
 * request at all, so there is nothing for a malicious client to spoof.
 * Missing customer / empty items are legitimate drafts: ValidationEngine
 * reports them as results, not as a 422 here.
 */
class ValidateInvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-invoicing');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'payment_type' => ['nullable', Rule::in(['contado', 'credito'])],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'items' => ['sometimes', 'array'],
            'items.*.product_service_id' => ['nullable', 'integer'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['nullable', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric'],
            'items.*.discount_percent' => ['nullable', 'numeric'],
            'items.*.tax_ids' => ['nullable', 'array'],
            'items.*.tax_ids.*' => ['integer'],
        ];
    }
}
