<?php

namespace App\Http\Requests\Settings;

use App\Enums\DocumentType;
use App\Services\Numbering\NumberingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NumberingResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-numbering');
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(array_map(fn (DocumentType $type) => $type->value, DocumentType::cases()))],
            'authorization_number_simulated' => ['required', 'string', 'max:50'],
            'prefix' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z0-9]{1,4}$/'],
            'range_from' => ['required', 'integer', 'min:1'],
            'range_to' => ['required', 'integer', 'min:1', 'gte:range_from'],
            'current_consecutive' => ['required', 'integer', 'min:1', 'gte:range_from', 'lte:range_to'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'simulated_technical_key' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $isActive = $this->boolean('is_active', true);

            if (! $isActive) {
                return;
            }

            $excludingId = $this->route('numberingResolution')?->id;

            $overlaps = app(NumberingService::class)->hasOverlappingActiveRange(
                companyId: $this->user()->company_id,
                documentType: $this->input('document_type'),
                rangeFrom: (int) $this->input('range_from'),
                rangeTo: (int) $this->input('range_to'),
                excludingId: $excludingId,
            );

            if ($overlaps) {
                $validator->errors()->add(
                    'range_from',
                    'El rango se solapa con otra resolución activa del mismo tipo de documento.'
                );
            }
        });
    }
}
