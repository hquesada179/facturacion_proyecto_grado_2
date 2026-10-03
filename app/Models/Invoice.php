<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToCompany;

    /**
     * number, status, validation_status, subtotal, tax_total, total,
     * simulated_dian_code, numbering_resolution_id, simulated_cufe,
     * issued_at, validation_at, dian_simulation_result/message and the
     * issuer/customer snapshots are intentionally excluded: they are only
     * ever computed and assigned by InvoiceRecalculationService /
     * IssueInvoiceService, never taken directly from request input.
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'issue_date',
        'due_date',
        'payment_type',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'validation_at' => 'datetime',
            'issuer_snapshot' => 'array',
            'customer_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->status ??= InvoiceStatus::Draft;
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function numberingResolution(): BelongsTo
    {
        return $this->belongsTo(NumberingResolution::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class)->latest();
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
