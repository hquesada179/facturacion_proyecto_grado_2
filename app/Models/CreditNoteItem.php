<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $fillable = [
        'credit_note_id',
        'invoice_item_id',
        'product_code',
        'description',
        'unit',
        'original_quantity',
        'credited_quantity',
        'unit_price',
        'discount_percent',
        'discount_total',
        'taxable_base',
        'tax_total',
        'line_total',
        'tax_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'original_quantity' => 'decimal:2',
            'credited_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'line_total' => 'decimal:2',
            'tax_snapshot' => 'array',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
