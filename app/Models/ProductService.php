<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductService extends Model
{
    protected $fillable = [
        'company_id',
        'tax_id',
        'sku',
        'name',
        'type',
        'unit',
        'price',
        'status',
        'tax_included',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_included' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
