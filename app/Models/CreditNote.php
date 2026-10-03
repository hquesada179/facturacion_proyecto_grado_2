<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use BelongsToCompany;

    public const REASONS = [
        'PROTO_TOTAL_VOID' => 'Anulación total (prototipo)',
        'PROTO_PARTIAL_RETURN' => 'Devolución parcial (prototipo)',
        'PROTO_VALUE_CORRECTION' => 'Corrección de valor (prototipo)',
        'PROTO_LATER_DISCOUNT' => 'Descuento posterior (prototipo)',
    ];

    /**
     * number, prefix, status, totals, simulated DIAN/CUFE fields and PDF
     * metadata are intentionally excluded: they are computed and assigned
     * by the credit-note service layer, never taken directly from request
     * input.
     */
    protected $fillable = [
        'invoice_id',
        'user_id',
        'reason',
        'reason_code',
        'reason_text',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'validation_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $creditNote): void {
            $creditNote->status ??= CreditNoteStatus::Draft;
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
