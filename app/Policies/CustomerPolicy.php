<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage-invoicing');
    }

    /**
     * "Consumidor Final" is the prototype's fixed placeholder customer
     * (see CustomerService::ensureFinalConsumer) — editing it could
     * repurpose it into a regular customer while is_final_consumer stays
     * true, and deleting it would leave the company without one at all.
     * Neither action is ever legitimate, so both are denied outright
     * rather than relying on the user to avoid clicking them.
     */
    public function update(User $user, Customer $customer): bool
    {
        return ! $customer->is_final_consumer
            && $user->can('manage-invoicing')
            && $user->company_id === $customer->company_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return ! $customer->is_final_consumer
            && $user->can('manage-invoicing')
            && $user->company_id === $customer->company_id;
    }
}
