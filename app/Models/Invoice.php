<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToCompany;

    /**
     * number, status, validation_status, subtotal, tax_total, total and
     * simulated_dian_code are intentionally excluded: they will be
     * computed and assigned by the invoicing service layer, never taken
     * directly from request input.
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'issue_date',
        'due_date',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
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
