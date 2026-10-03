<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of one tax applied to one invoice line (code/name/rate frozen
 * at calculation time — see create_invoice_item_taxes_table migration).
 */
class InvoiceItemTax extends Model
{
    protected $fillable = [
        'invoice_item_id',
        'tax_id',
        'code',
        'name',
        'base',
        'rate',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
            'rate' => 'decimal:2',
            'value' => 'decimal:2',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
