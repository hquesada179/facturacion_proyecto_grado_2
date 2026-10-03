<?php

namespace App\Services\Assistant;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Invoices\InvoiceDraftContextBuilder;
use App\Services\Invoices\Validation\ValidationEngine;
use Illuminate\Support\Facades\Gate;

class AssistantContextBuilder
{
    public function __construct(
        private readonly InvoiceDraftContextBuilder $invoiceContextBuilder,
        private readonly ValidationEngine $validationEngine,
    ) {}

    public function build(User $user, string $screen, ?string $resourceType = null, ?int $resourceId = null): array
    {
        $context = [
            'screen' => $screen,
            'user_role' => $user->role->value,
            'company' => [
                'id' => $user->company_id,
                'name' => $user->company?->name,
            ],
            'resource' => [
                'type' => $resourceType,
                'id' => $resourceId,
                'found' => false,
            ],
            'critical_actions_require_confirmation' => true,
        ];

        if ($resourceType === 'invoice' && $resourceId !== null) {
            return $this->withInvoiceContext($context, $user, $resourceId);
        }

        if ($resourceType === 'credit_note' && $resourceId !== null) {
            return $this->withCreditNoteContext($context, $user, $resourceId);
        }

        if ($resourceType === 'customer' && $resourceId !== null) {
            return $this->withCustomerContext($context, $user, $resourceId);
        }

        if ($resourceType === 'product' && $resourceId !== null) {
            return $this->withProductContext($context, $user, $resourceId);
        }

        return $context;
    }

    private function withInvoiceContext(array $context, User $user, int $invoiceId): array
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->with('customer', 'creditNotes')
            ->where('company_id', $user->company_id)
            ->whereKey($invoiceId)
            ->first();

        if (! $invoice || Gate::forUser($user)->denies('view', $invoice)) {
            return $context;
        }

        $validation = $this->validationEngine->run($this->invoiceContextBuilder->buildFromInvoice($invoice));

        $context['resource']['found'] = true;
        $context['invoice'] = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'customer' => $invoice->customer?->name,
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'total' => (float) $invoice->total,
            'credit_notes_count' => $invoice->creditNotes->count(),
        ];
        $context['validation'] = [
            'blocking_errors' => $validation->blockingCount(),
            'warnings' => $validation->warningCount(),
        ];

        return $context;
    }

    private function withCreditNoteContext(array $context, User $user, int $creditNoteId): array
    {
        $creditNote = CreditNote::withoutGlobalScopes()
            ->with('invoice')
            ->where('company_id', $user->company_id)
            ->whereKey($creditNoteId)
            ->first();

        if (! $creditNote || Gate::forUser($user)->denies('view', $creditNote)) {
            return $context;
        }

        $context['resource']['found'] = true;
        $context['credit_note'] = [
            'id' => $creditNote->id,
            'number' => $creditNote->number,
            'status' => $creditNote->status->value,
            'status_label' => $creditNote->status->label(),
            'invoice_id' => $creditNote->invoice_id,
            'invoice_number' => $creditNote->invoice?->number,
            'total' => (float) $creditNote->total,
        ];

        return $context;
    }

    private function withCustomerContext(array $context, User $user, int $customerId): array
    {
        $customer = Customer::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($customerId)
            ->first();

        if (! $customer || Gate::forUser($user)->denies('view', $customer)) {
            return $context;
        }

        $context['resource']['found'] = true;
        $context['customer'] = [
            'id' => $customer->id,
            'name' => $customer->name,
            'identification_type' => $customer->identification_type,
            'status' => $customer->status,
        ];

        return $context;
    }

    private function withProductContext(array $context, User $user, int $productId): array
    {
        $product = ProductService::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($productId)
            ->first();

        if (! $product || Gate::forUser($user)->denies('view', $product)) {
            return $context;
        }

        $context['resource']['found'] = true;
        $context['product'] = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'status' => $product->status->value,
        ];

        return $context;
    }
}
