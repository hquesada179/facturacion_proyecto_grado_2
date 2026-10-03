<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Fixed catalog of DIAN fiscal-responsibility codes offered in the
     * company settings form. Simplified subset for the prototype, not the
     * full RUT responsibility table.
     */
    public const FISCAL_RESPONSIBILITIES = [
        'O-13' => 'O-13 Gran contribuyente',
        'O-15' => 'O-15 Autorretenedor',
        'O-23' => 'O-23 Agente de retención IVA',
        'O-47' => 'O-47 Régimen simple de tributación',
        'O-48' => 'O-48 Impuesto sobre las ventas - IVA',
        'R-99-PN' => 'R-99-PN No aplica - Otros',
    ];

    public const CURRENCIES = ['COP', 'USD'];

    /**
     * nit_dv is intentionally excluded: it is always recalculated from
     * `nit` via NitDvCalculator, never taken directly from request input.
     */
    protected $fillable = [
        'name',
        'legal_name',
        'person_type',
        'nit',
        'email',
        'phone',
        'address',
        'city',
        'department',
        'country',
        'tax_regime',
        'fiscal_responsibilities',
        'main_tax_id',
        'currency',
        'is_test_environment',
        'invoice_prefix',
        'simulation_enabled',
    ];

    protected function casts(): array
    {
        return [
            'simulation_enabled' => 'boolean',
            'is_test_environment' => 'boolean',
            'fiscal_responsibilities' => 'array',
        ];
    }

    public function mainTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'main_tax_id');
    }

    public function numberingResolutions(): HasMany
    {
        return $this->hasMany(NumberingResolution::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function productServices(): HasMany
    {
        return $this->hasMany(ProductService::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }
}
