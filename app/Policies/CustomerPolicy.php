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

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('manage-invoicing') && $user->company_id === $customer->company_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('manage-invoicing') && $user->company_id === $customer->company_id;
    }
}
