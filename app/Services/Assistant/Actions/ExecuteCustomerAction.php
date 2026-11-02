<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantActionProposal;
use App\Models\Customer;
use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Confirm-time execution for `create_customer`. Re-runs the exact same
 * validation (another customer with that identification could have
 * been created between propose and confirm) before calling the real
 * domain service — never trusts that the proposal is still valid just
 * because it was valid a few minutes ago.
 */
class ExecuteCustomerAction
{
    public function __construct(
        private readonly ProposeCustomerAction $validator,
        private readonly CustomerService $customers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, AssistantActionProposal $proposal): array
    {
        if (Gate::forUser($user)->denies('create', Customer::class)) {
            throw ValidationException::withMessages(['action' => 'Tu rol no tiene permiso para crear clientes.']);
        }

        $data = $proposal->payload;
        $errors = $this->validator->validate($user, $data);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'customer' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
            ]);
        }

        $customer = $this->customers->createFromData($data);

        return [
            'resource_type' => 'customer',
            'resource_id' => $customer->id,
            'name' => $customer->name,
            'identification' => $customer->identification_type.' '.$customer->identification_number,
            'status' => $customer->status,
        ];
    }
}
