<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\WhatsappConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only place wa_id <-> user_id <-> company_id is ever persisted
 * (see Fase 1 report — VYA deliberately never stores this mapping).
 * `BelongsToCompany` gives the Settings screens (real web session,
 * real authenticated user) the same automatic company scoping every
 * other owned resource gets; the bridge (no web session, see
 * CompanyScope's own early-return when Auth::check() is false) never
 * relies on it and always filters explicitly by wa_id/company_id/user_id
 * itself — same discipline the Assistant Tools already use.
 */
class WhatsappConnection extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<WhatsappConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'wa_id',
        'active_wa_id',
        'phone_number_id',
        'assistant_conversation_id',
        'verified_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * "+57 ••• ••• 1234" — never reveals the true middle-digit count,
     * just a fixed-width mask, so the UI can't be used to infer the
     * number's real length either.
     */
    public function maskedWaId(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->wa_id) ?? '';
        $last4 = substr($digits, -4);
        $country = substr($digits, 0, max(0, strlen($digits) - 10)) ?: substr($digits, 0, 2);

        return sprintf('+%s ••• ••• %s', $country, $last4);
    }
}
