<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Implementing MustVerifyEmail activates the framework's standard
 * hasVerifiedEmail()/markEmailAsVerified() (already provided by the
 * Authenticatable base class via email_verified_at — no new column) so
 * EmailVerificationController and the `verified` middleware recognize a
 * user exactly the way Laravel expects. Every existing factory/seeded
 * user already has email_verified_at set to now(), so this has no
 * effect on them — only newly self-registered, not-yet-OTP-verified
 * users are affected, and only on the routes that opt into the
 * `verified` middleware (just /onboarding for now, see routes/web.php).
 */
#[Fillable(['company_id', 'name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function invoiceEvents(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
    }

    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }

    public function isAdministrador(): bool
    {
        return $this->role === UserRole::Administrador;
    }

    public function isFacturador(): bool
    {
        return $this->role === UserRole::Facturador;
    }

    public function isContador(): bool
    {
        return $this->role === UserRole::Contador;
    }

    public function isAuditor(): bool
    {
        return $this->role === UserRole::Auditor;
    }
}
