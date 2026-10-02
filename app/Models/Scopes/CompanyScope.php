<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts every query on the owning model to the authenticated user's
 * company. Fails closed: a user without a company sees nothing rather
 * than falling back to an unscoped (cross-company) query.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $companyId = Auth::user()->company_id;

        if ($companyId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
