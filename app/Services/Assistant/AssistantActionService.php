<?php

namespace App\Services\Assistant;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\IssueInvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssistantActionService
{
    public function __construct(private readonly IssueInvoiceService $issueInvoiceService) {}

    /**
     * @return array<string, mixed>
     */
    public function createConfirmation(User $user, string $action, string $resourceType, int $resourceId, string $summary): array
    {
        $token = Str::random(48);

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'summary' => $summary,
        ], now()->addMinutes(10));

        return [
            'requires_confirmation' => true,
            'action' => $action,
            'summary' => $summary,
            'token' => $token,
        ];
    }

    public function confirm(User $user, string $token): AssistantResponse
    {
        $payload = Cache::pull($this->cacheKey($token));

        if (! is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== $user->id
            || (int) ($payload['company_id'] ?? 0) !== $user->company_id
        ) {
            throw ValidationException::withMessages([
                'token' => 'La confirmación no es válida o ya expiró.',
            ]);
        }

        if (($payload['action'] ?? null) === 'issue_invoice' && ($payload['resource_type'] ?? null) === 'invoice') {
            return $this->confirmIssueInvoice($user, (int) $payload['resource_id']);
        }

        throw ValidationException::withMessages([
            'action' => 'La acción solicitada no está habilitada desde el asistente.',
        ]);
    }

    private function confirmIssueInvoice(User $user, int $invoiceId): AssistantResponse
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($invoiceId)
            ->first();

        if (! $invoice || Gate::forUser($user)->denies('update', $invoice)) {
            throw new AuthorizationException;
        }

        if ($invoice->status !== InvoiceStatus::LocallyValidated) {
            throw ValidationException::withMessages([
                'invoice' => 'La factura debe estar validada localmente antes de emitirla.',
            ]);
        }

        $issued = $this->issueInvoiceService->issue($invoice);

        return new AssistantResponse(
            message: $issued->status === InvoiceStatus::Issued
                ? "Factura {$issued->number} emitida correctamente después de confirmación humana."
                : 'La emisión fue procesada por el servicio de dominio y terminó en estado '.$issued->status->label().'.',
            metadata: ['invoice_id' => $issued->id, 'status' => $issued->status->value],
        );
    }

    private function cacheKey(string $token): string
    {
        return 'assistant_confirmation:'.$token;
    }
}
