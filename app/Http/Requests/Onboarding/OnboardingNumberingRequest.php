<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\DocumentType;
use App\Services\Numbering\NumberingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The onboarding Billing step's simplified subset of
 * NumberingResolutionRequest's fields (prefix/range/validity/active only
 * — document_type/authorization_number_simulated/current_consecutive/
 * simulated_technical_key are filled in with defaults by
 * OnboardingService::ensureInitialNumberingResolution(), never asked of
 * a brand-new user). The overlap check reuses
 * NumberingService::hasOverlappingActiveRange() — the exact same method
 * the real settings form's request already calls — so this never
 * re-implements that rule.
 */
class OnboardingNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('manage-numbering');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prefix' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z0-9]{1,4}$/'],
            'range_from' => ['required', 'integer', 'min:1'],
            'range_to' => ['required', 'integer', 'min:1', 'gte:range_from'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_active', true)) {
                return;
            }

            $overlaps = app(NumberingService::class)->hasOverlappingActiveRange(
                companyId: $this->user()->company_id,
                documentType: DocumentType::Invoice->value,
                rangeFrom: (int) $this->input('range_from'),
                rangeTo: (int) $this->input('range_to'),
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
