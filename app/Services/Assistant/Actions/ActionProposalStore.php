<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantActionProposal;
use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generalized persistence/lifecycle for every assistant action proposal
 * — the single mechanism behind "la IA puede interpretar y preparar,
 * Laravel valida y autoriza, el usuario revisa, solo tras confirmación
 * explícita se ejecuta" (IA 2.0-D), reused by `issue_invoice` (carried
 * over from IA 2.0-A/B) and the four new IA 2.0-D actions alike — never
 * a second, parallel confirmation system.
 *
 * Security properties enforced here, not left to callers:
 *  - only a sha256 hash of the token is ever persisted; the raw token
 *    is returned once, in the API response, and never logged;
 *  - every lookup re-checks user_id AND company_id, never just one;
 *  - a pending -> confirmed/cancelled transition is a single
 *    conditional UPDATE ("WHERE status = pending"), so two concurrent
 *    requests (double-click, replay) can never both succeed — exactly
 *    one wins, the other gets a clean rejection, never a duplicate;
 *  - expiry is checked and converted to a terminal `expired` state the
 *    moment it's observed, not left to decay silently.
 */
class ActionProposalStore
{
    private const DEFAULT_TTL_MINUTES = 15;

    public function __construct(private readonly AssistantActionAuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $payload  Resolved identifiers +
     *   user-specified quantities only — never a trusted price/total.
     * @param  array<string, mixed>  $preview  Display-only snapshot,
     *   also used as the "what the user was shown" baseline for
     *   confirm-time material-change detection.
     */
    public function create(
        User $user,
        ?AssistantConversation $conversation,
        string $action,
        array $payload,
        array $preview,
        int $ttlMinutes = self::DEFAULT_TTL_MINUTES,
    ): array {
        $raw = Str::random(48);

        $proposal = new AssistantActionProposal([
            'user_id' => $user->id,
            'assistant_conversation_id' => $conversation?->id,
            'action' => $action,
            'status' => AssistantActionProposal::STATUS_PENDING,
            'token_hash' => hash('sha256', $raw),
            'payload' => $payload,
            'preview' => $preview,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
        $proposal->company_id = $user->company_id;
        $proposal->save();

        $this->audit->proposed($user, $proposal);

        return ['proposal' => $proposal, 'token' => $raw];
    }

    /**
     * Atomically moves a still-pending, still-fresh, correctly-owned
     * proposal to `confirmed` and returns it. Throws on anything else —
     * unknown token, wrong user/company, already used, expired.
     */
    public function claim(User $user, string $token): AssistantActionProposal
    {
        $proposal = $this->findOwned($user, $token);

        $claimed = AssistantActionProposal::withoutGlobalScopes()
            ->whereKey($proposal->id)
            ->where('status', AssistantActionProposal::STATUS_PENDING)
            ->update(['status' => AssistantActionProposal::STATUS_CONFIRMED, 'confirmed_at' => now()]);

        if ($claimed !== 1) {
            throw ValidationException::withMessages([
                'token' => 'La confirmación no es válida o ya fue utilizada.',
            ]);
        }

        return $proposal->refresh();
    }

    /**
     * Read-only lookup for "continuemos con la factura" — redisplays an
     * already-built, still-pending, still-fresh, correctly-owned
     * proposal without claiming or mutating it in any way. Returns null
     * for anything else (wrong owner, cancelled/confirmed/failed,
     * expired) rather than throwing — callers use this to decide
     * whether a resume is even possible, not to enforce access control
     * (claim()/cancel() still do that at the point something is
     * actually attempted).
     */
    public function peek(User $user, string $token): ?AssistantActionProposal
    {
        $proposal = AssistantActionProposal::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($proposal === null
            || (int) $proposal->user_id !== $user->id
            || (int) $proposal->company_id !== $user->company_id
            || ! $proposal->isPending()
            || $proposal->isExpired()
        ) {
            return null;
        }

        return $proposal;
    }

    public function cancel(User $user, string $token): AssistantActionProposal
    {
        $proposal = $this->findOwned($user, $token);

        $cancelled = AssistantActionProposal::withoutGlobalScopes()
            ->whereKey($proposal->id)
            ->where('status', AssistantActionProposal::STATUS_PENDING)
            ->update(['status' => AssistantActionProposal::STATUS_CANCELLED, 'cancelled_at' => now()]);

        if ($cancelled !== 1) {
            throw ValidationException::withMessages([
                'token' => 'La confirmación no es válida o ya fue utilizada.',
            ]);
        }

        $proposal = $proposal->refresh();
        $this->audit->cancelled($user, $proposal);

        return $proposal;
    }

    /**
     * Called by the executor once a claimed proposal's domain creation
     * actually succeeds — never earlier, never speculatively.
     *
     * @param  array<string, mixed>  $result
     */
    public function recordSuccess(User $user, AssistantActionProposal $proposal, array $result): void
    {
        $proposal->forceFill(['result' => $result])->save();
        $this->audit->confirmed($user, $proposal);
    }

    /**
     * Called when a claimed proposal could not actually be executed —
     * a material change was detected, or the domain layer itself
     * rejected it. The proposal is already `confirmed` (claimed) by
     * this point and must never be retried silently, so this moves it
     * to a `failed` terminal state instead of leaving it claimable or
     * reverting it to pending (which would just invite a second,
     * equally-stale retry).
     */
    public function recordFailure(User $user, AssistantActionProposal $proposal, string $reason): void
    {
        $proposal->forceFill([
            'status' => AssistantActionProposal::STATUS_FAILED,
            'result' => ['error' => $reason],
        ])->save();
        $this->audit->failed($user, $proposal, $reason);
    }

    private function findOwned(User $user, string $token): AssistantActionProposal
    {
        $proposal = AssistantActionProposal::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($proposal === null
            || (int) $proposal->user_id !== $user->id
            || (int) $proposal->company_id !== $user->company_id
        ) {
            throw ValidationException::withMessages([
                'token' => 'La confirmación no es válida o ya expiró.',
            ]);
        }

        if ($proposal->status === AssistantActionProposal::STATUS_PENDING && $proposal->isExpired()) {
            AssistantActionProposal::withoutGlobalScopes()
                ->whereKey($proposal->id)
                ->where('status', AssistantActionProposal::STATUS_PENDING)
                ->update(['status' => AssistantActionProposal::STATUS_EXPIRED]);

            $this->audit->expired($user, $proposal->refresh());

            throw ValidationException::withMessages([
                'token' => 'Esta propuesta expiró. Debo recalcularla antes de continuar.',
            ]);
        }

        if (! $proposal->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'La confirmación no es válida o ya fue utilizada.',
            ]);
        }

        return $proposal;
    }
}
