<?php

namespace App\Services\Assistant\Tools;

use App\Models\CreditNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GetCreditNoteDetailsTool
{
    public function handle(User $user, ?int $creditNoteId = null, ?string $number = null): array
    {
        $query = CreditNote::withoutGlobalScopes()
            ->with('invoice.customer')
            ->where('company_id', $user->company_id);

        if ($creditNoteId !== null) {
            $query->whereKey($creditNoteId);
        } elseif ($number !== null && trim($number) !== '') {
            $query->where('number', trim($number));
        } else {
            return ['tool' => 'credit_note_details', 'found' => false];
        }

        $creditNote = $query->first();

        if (! $creditNote || Gate::forUser($user)->denies('view', $creditNote)) {
            return ['tool' => 'credit_note_details', 'found' => false];
        }

        return [
            'tool' => 'credit_note_details',
            'found' => true,
            'credit_note' => [
                'id' => $creditNote->id,
                'number' => $creditNote->number,
                'invoice_number' => $creditNote->invoice?->number,
                'customer' => $creditNote->invoice?->customer?->name,
                'status' => $creditNote->status->value,
                'status_label' => $creditNote->status->label(),
                'reason' => $creditNote->reason_text ?: $creditNote->reason,
                'total' => (float) $creditNote->total,
            ],
        ];
    }
}
