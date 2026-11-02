<?php

namespace App\Services\Assistant;

use App\Enums\InvoiceStatus;
use App\Models\AssistantActionProposal;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Assistant\Actions\ActionProposalStore;
use App\Services\Assistant\Actions\ExecuteCreditNoteDraftAction;
use App\Services\Assistant\Actions\ExecuteCustomerAction;
use App\Services\Assistant\Actions\ExecuteInvoiceDraftAction;
use App\Services\Assistant\Actions\ExecuteProductAction;
use App\Services\Invoices\IssueInvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The single confirmation mechanism for every assistant action — the
 * IA 2.0-A `issue_invoice` critical action and the four IA 2.0-D
 * propose-and-confirm actions alike, all backed by the same persisted,
 * single-use AssistantActionProposal (see ActionProposalStore). There
 * is deliberately no second, parallel confirmation path.
 */
class AssistantActionService
{
    public function __construct(
        private readonly IssueInvoiceService $issueInvoiceService,
        private readonly ActionProposalStore $proposals,
        private readonly ExecuteInvoiceDraftAction $executeInvoiceDraft,
        private readonly ExecuteCustomerAction $executeCustomer,
        private readonly ExecuteProductAction $executeProduct,
        private readonly ExecuteCreditNoteDraftAction $executeCreditNoteDraft,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function createConfirmation(User $user, string $action, string $resourceType, int $resourceId, string $summary): array
    {
        $created = $this->proposals->create(
            $user,
            null,
            $action,
            ['resource_type' => $resourceType, 'resource_id' => $resourceId],
            ['title' => 'Confirmación requerida', 'summary' => $summary, 'fields' => [], 'lines' => [], 'totals' => [], 'warnings' => []],
            ttlMinutes: 10,
        );

        return [
            'requires_confirmation' => true,
            'action' => $action,
            'summary' => $summary,
            'token' => $created['token'],
        ];
    }

    public function confirm(User $user, string $token): AssistantResponse
    {
        $proposal = $this->proposals->claim($user, $token);

        try {
            $result = $this->execute($user, $proposal);
        } catch (ValidationException|AuthorizationException $exception) {
            $this->proposals->recordFailure($user, $proposal, $this->safeFailureReason($exception));

            throw $exception;
        }

        $this->proposals->recordSuccess($user, $proposal, $result);

        return $this->responseFor($proposal->action, $result);
    }

    public function cancel(User $user, string $token): AssistantResponse
    {
        $this->proposals->cancel($user, $token);

        return new AssistantResponse('Propuesta cancelada. Aún no se ha realizado ningún cambio.');
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(User $user, AssistantActionProposal $proposal): array
    {
        return match ($proposal->action) {
            'issue_invoice' => $this->confirmIssueInvoice($user, $proposal),
            'create_invoice_draft' => $this->executeInvoiceDraft->handle($user, $proposal),
            'create_customer' => $this->executeCustomer->handle($user, $proposal),
            'create_product' => $this->executeProduct->handle($user, $proposal),
            'create_credit_note_draft' => $this->executeCreditNoteDraft->handle($user, $proposal),
            default => throw ValidationException::withMessages([
                'action' => 'La acción solicitada no está habilitada desde el asistente.',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmIssueInvoice(User $user, AssistantActionProposal $proposal): array
    {
        $invoiceId = (int) ($proposal->payload['resource_id'] ?? 0);

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

        return [
            'resource_type' => 'invoice',
            'resource_id' => $issued->id,
            'number' => $issued->number,
            'status' => $issued->status->value,
            'status_label' => $issued->status->label(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function responseFor(string $action, array $result): AssistantResponse
    {
        return match ($action) {
            'issue_invoice' => new AssistantResponse(
                message: $result['status'] === InvoiceStatus::Issued->value
                    ? "Factura {$result['number']} emitida correctamente después de confirmación humana."
                    : 'La emisión fue procesada por el servicio de dominio y terminó en estado '.$result['status_label'].'.',
                metadata: ['invoice_id' => $result['resource_id'], 'status' => $result['status']],
            ),
            'create_invoice_draft' => new AssistantResponse(
                message: 'Borrador creado correctamente.',
                components: [$this->invoiceCreatedCard($result)],
            ),
            'create_customer' => new AssistantResponse(
                message: 'Cliente creado.',
                components: [$this->customerCreatedCard($result)],
            ),
            'create_product' => new AssistantResponse(
                message: 'Producto creado.',
                components: [$this->productCreatedCard($result)],
            ),
            'create_credit_note_draft' => new AssistantResponse(
                message: 'Borrador de nota crédito creado.',
                components: [$this->creditNoteCreatedCard($result)],
            ),
            default => new AssistantResponse('Acción completada.'),
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function invoiceCreatedCard(array $result): array
    {
        return [
            'type' => 'invoice_list',
            'items' => [[
                'id' => $result['resource_id'],
                'number' => $result['number'],
                'customer' => $result['customer'] ?? null,
                'status' => $result['status'],
                'status_label' => $result['status_label'],
                'total' => $result['total'] ?? null,
                'date' => null,
                'url' => route('invoices.draft.items', $result['resource_id']),
                'pdf_url' => null,
                'traceability_url' => null,
            ]],
            'total_matches' => 1,
            'showing' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function customerCreatedCard(array $result): array
    {
        return [
            'type' => 'customer_list',
            'items' => [[
                'id' => $result['resource_id'],
                'name' => $result['name'],
                'identification' => $result['identification'] ?? null,
                'status' => $result['status'] ?? null,
                'documents_count' => 0,
                'url' => route('customers.show', $result['resource_id']),
            ]],
            'total_matches' => 1,
            'showing' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function productCreatedCard(array $result): array
    {
        return [
            'type' => 'product_list',
            'items' => [[
                'id' => $result['resource_id'],
                'sku' => $result['sku'] ?? null,
                'name' => $result['name'],
                'price' => $result['price'] ?? null,
                'status' => $result['status'] ?? null,
                'taxes' => null,
                'url' => route('products.show', $result['resource_id']),
            ]],
            'total_matches' => 1,
            'showing' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function creditNoteCreatedCard(array $result): array
    {
        return [
            'type' => 'credit_note_list',
            'items' => [[
                'id' => $result['resource_id'],
                'number' => $result['number'],
                'invoice_number' => $result['invoice_number'] ?? null,
                'customer' => null,
                'status' => $result['status'],
                'status_label' => $result['status_label'],
                'total' => $result['total'] ?? null,
                'date' => null,
                'url' => route('credit-notes.show', $result['resource_id']),
                'pdf_url' => null,
            ]],
            'total_matches' => 1,
            'showing' => 1,
        ];
    }

    /**
     * Never surfaces the raw exception message for an AuthorizationException
     * (framework default text carries no sensitive detail either, but this
     * keeps the audit log's `failed` reason consistently short/safe
     * regardless of exception type).
     */
    private function safeFailureReason(ValidationException|AuthorizationException $exception): string
    {
        return $exception instanceof ValidationException
            ? ($exception->validator->errors()->first() ?: 'validation_failed')
            : 'authorization_denied';
    }
}
