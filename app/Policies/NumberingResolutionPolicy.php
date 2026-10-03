<?php

namespace App\Policies;

use App\Models\NumberingResolution;
use App\Models\User;

class NumberingResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-numbering');
    }

    public function view(User $user, NumberingResolution $resolution): bool
    {
        return $user->can('manage-numbering') && $user->company_id === $resolution->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage-numbering');
    }

    public function update(User $user, NumberingResolution $resolution): bool
    {
        return $user->can('manage-numbering') && $user->company_id === $resolution->company_id;
    }
}
