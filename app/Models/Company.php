<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'nit',
        'email',
        'phone',
        'address',
        'city',
        'tax_regime',
        'invoice_prefix',
        'simulation_enabled',
    ];

    protected function casts(): array
    {
        return [
            'simulation_enabled' => 'boolean',
        ];
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
