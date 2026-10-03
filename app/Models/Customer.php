<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public const IDENTIFICATION_TYPES = [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'NIT' => 'NIT',
        'PAS' => 'Pasaporte',
        'TI' => 'Tarjeta de identidad',
        'RC' => 'Registro civil',
    ];

    public const STATUSES = ['active' => 'Activo', 'inactive' => 'Inactivo'];

    /**
     * dv is intentionally excluded: it is always recalculated from
     * identification_number via NitDvCalculator when identification_type
     * is NIT, never taken directly from request input.
     */
    protected $fillable = [
        'person_type',
        'identification_type',
        'identification_number',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'department',
        'country',
        'tax_responsibility',
        'tax_id',
        'status',
        'is_final_consumer',
    ];

    protected function casts(): array
    {
        return [
            'is_final_consumer' => 'boolean',
        ];
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('identification_number', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }
}
