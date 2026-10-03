<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage-invoicing');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('manage-invoicing') && $user->company_id === $invoice->company_id;
    }

    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id
            && $invoice->status === InvoiceStatus::Issued;
    }

    public function sendSimulatedDelivery(User $user, Invoice $invoice): bool
    {
        return $user->can('manage-invoicing')
            && $user->company_id === $invoice->company_id
            && $invoice->status === InvoiceStatus::Issued;
    }
}
