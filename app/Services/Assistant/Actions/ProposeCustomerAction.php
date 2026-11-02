<?php

namespace App\Services\Assistant\Actions;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Interpretation + validation + pure preview for "Prepara un cliente
 * llamado Juan Pérez con CC 123456" — never saves a Customer row. Runs
 * the exact same uniqueness/format rules StoreCustomerRequest uses, so
 * a duplicate or malformed identification is caught here, not only
 * (again) at confirm time.
 */
class ProposeCustomerAction
{
    /**
     * @param  array{name: ?string, identification_type: ?string, identification_number: ?string, person_type: ?string}  $intent
     * @return array<string, mixed>
     */
    public function handle(User $user, array $intent): array
    {
        if (Gate::forUser($user)->denies('create', Customer::class)) {
            return ['status' => 'denied', 'reason' => 'Tu rol no tiene permiso para crear clientes.'];
        }

        $missing = [];

        if (! $intent['name']) {
            $missing[] = 'nombre';
        }

        if (! $intent['identification_type'] || ! $intent['identification_number']) {
            $missing[] = 'tipo y número de identificación';
        }

        if ($missing !== []) {
            return [
                'status' => 'missing',
                'missing_fields' => $missing,
                'reason' => 'Necesito el '.implode(' y el ', $missing).'.',
            ];
        }

        $data = [
            'person_type' => $intent['person_type'] ?? 'natural',
            'identification_type' => $intent['identification_type'],
            'identification_number' => Customer::normalizeIdentificationNumber($intent['identification_number']),
            'name' => $intent['name'],
            'status' => 'active',
        ];

        $errors = $this->validate($user, $data);

        if ($errors !== []) {
            return ['status' => 'invalid', 'reason' => implode(' ', $errors)];
        }

        $preview = [
            'title' => 'Preparar cliente',
            'summary' => 'Cliente '.$data['name'].' ('.$data['identification_type'].' '.$data['identification_number'].')',
            'fields' => [
                ['label' => 'Nombre', 'value' => $data['name']],
                ['label' => 'Tipo de persona', 'value' => $data['person_type'] === 'juridica' ? 'Jurídica' : 'Natural'],
                ['label' => 'Identificación', 'value' => $data['identification_type'].' '.$data['identification_number']],
            ],
            'lines' => [],
            'totals' => [],
            'warnings' => [],
        ];

        return ['status' => 'ready', 'payload' => $data, 'preview' => $preview];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validate(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'person_type' => ['required', Rule::in(['natural', 'juridica'])],
            'identification_type' => ['required', Rule::in(array_keys(Customer::IDENTIFICATION_TYPES))],
            'identification_number' => [
                'required', 'string', 'max:20',
                Rule::unique('customers')->where('company_id', $user->company_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
        ]);

        if (! $validator->fails()
            && in_array($data['identification_type'], Customer::NUMERIC_IDENTIFICATION_TYPES, true)
            && ! ctype_digit((string) $data['identification_number'])
        ) {
            return ['Para este tipo de identificación, el número solo puede contener dígitos.'];
        }

        return $validator->fails() ? $validator->errors()->all() : [];
    }
}
