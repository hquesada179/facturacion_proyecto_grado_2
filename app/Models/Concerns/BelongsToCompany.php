<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Shared multi-tenant behaviour for models owned by a company: every
 * query is scoped to the authenticated user's company (see CompanyScope)
 * and company_id is assigned automatically on creation, never through
 * mass assignment from request input.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model): void {
            if (! $model->company_id && Auth::check()) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
