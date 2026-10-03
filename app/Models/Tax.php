<?php

namespace App\Models;

use Database\Factories\TaxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    /** @use HasFactory<TaxFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'rate',
        'type',
        'calculation_type',
        'nature',
        'condition',
        'valid_from',
        'valid_until',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @deprecated Kept for historical data; use productServices() for new code. */
    public function legacyProductServices(): HasMany
    {
        return $this->hasMany(ProductService::class);
    }

    public function productServices(): BelongsToMany
    {
        return $this->belongsToMany(ProductService::class, 'product_service_tax');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->active()
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }

    public function scopeAvailableFor(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('company_id');

            if ($companyId !== null) {
                $query->orWhere('company_id', $companyId);
            }
        });
    }
}
