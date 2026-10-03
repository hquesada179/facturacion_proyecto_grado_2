<?php

namespace App\Policies;

use App\Models\Tax;
use App\Models\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-taxes');
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->can('manage-taxes') && ($tax->company_id === null || $tax->company_id === $user->company_id);
    }
}
