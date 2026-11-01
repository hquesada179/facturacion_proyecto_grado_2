<?php

namespace App\Policies;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->company_id === $creditNote->company_id;
    }

    /**
     * $invoice is optional so existing class-level checks
     * (`@can('create', CreditNote::class)`, `authorize('create',
     * CreditNote::class)`) keep working unchanged. Passing the invoice
     * (`@can('create', [CreditNote::class, $invoice])`) additionally
     * hides the action once that specific invoice has no remaining
     * balance to credit (Voided) — the same eligibility
     * InvoiceStatus::canReceiveCreditNote() already enforces server-side.
     */
    public function create(User $user, ?Invoice $invoice = null): bool
    {
        if (! $user->can('manage-invoicing')) {
            return false;
        }

        if ($invoice === null) {
            return true;
        }

        return $user->company_id === $invoice->company_id
            && $invoice->status->canReceiveCreditNote();
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $user->can('manage-invoicing')
            && $user->company_id === $creditNote->company_id
            && in_array($creditNote->status, [
                CreditNoteStatus::Draft,
                CreditNoteStatus::LocallyValidated,
                CreditNoteStatus::SimulatedRejected,
                CreditNoteStatus::TechnicalError,
            ], true);
    }

    public function issue(User $user, CreditNote $creditNote): bool
    {
        return $user->can('manage-invoicing')
            && $user->company_id === $creditNote->company_id
            && $creditNote->status === CreditNoteStatus::LocallyValidated;
    }

    public function downloadPdf(User $user, CreditNote $creditNote): bool
    {
        return $user->company_id === $creditNote->company_id
            && $creditNote->status === CreditNoteStatus::Issued;
    }
}
