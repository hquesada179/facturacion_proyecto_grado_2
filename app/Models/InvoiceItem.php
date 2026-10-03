<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_service_id',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'discount_total',
        'discount_percent',
        'taxable_base',
        'tax_rate',
        'tax_total',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    public function itemTaxes(): HasMany
    {
        return $this->hasMany(InvoiceItemTax::class);
    }
}
