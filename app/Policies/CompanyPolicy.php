<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('manage-company') && $user->company_id === $company->id;
    }
}
