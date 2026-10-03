<?php

namespace App\Policies;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
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

    public function create(User $user): bool
    {
        return $user->can('manage-invoicing');
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
