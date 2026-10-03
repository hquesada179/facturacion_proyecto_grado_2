<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only audit log. Nothing may rewrite or remove an event once
 * created — see save()/delete() overrides below.
 */
class InvoiceEvent extends Model
{
    protected $fillable = [
        'invoice_id',
        'user_id',
        'type',
        'from_status',
        'to_status',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new RuntimeException('Los eventos de trazabilidad son append-only y no se pueden modificar.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new RuntimeException('Los eventos de trazabilidad son append-only y no se pueden eliminar.');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
