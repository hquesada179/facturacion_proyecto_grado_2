<?php

namespace App\Services\Assistant\Tools;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceDraftContextBuilder;
use App\Services\Invoices\Validation\ValidationEngine;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\Gate;

class ExplainValidationTool
{
    public function __construct(
        private readonly InvoiceDraftContextBuilder $contextBuilder,
        private readonly ValidationEngine $engine,
        private readonly NumberingService $numbering,
    ) {}

    public function handle(User $user, int $invoiceId): array
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($invoiceId)
            ->first();

        if (! $invoice || Gate::forUser($user)->denies('view', $invoice)) {
            return ['tool' => 'explain_validation', 'found' => false];
        }

        $context = $this->contextBuilder->buildFromInvoice($invoice);
        $results = $this->engine->run($context);

        return [
            'tool' => 'explain_validation',
            'found' => true,
            'invoice_id' => $invoice->id,
            'blocking_count' => $results->blockingCount(),
            'warning_count' => $results->warningCount(),
            'results' => collect($results->all())->map(fn ($result): array => [
                'code' => $result->codigo,
                'rule' => $result->regla,
                'severity' => $result->severidad->value,
                'field' => $result->campo,
                'message' => $result->mensaje,
                'suggestion' => $result->sugerencia,
            ])->values()->all(),
            // Not a ValidationEngine rule — it's a separate condition
            // checked only at actual emission time (see
            // IssueInvoiceService::finishIssuing()) — so the assistant
            // needs it reported explicitly to be able to explain it.
            'has_active_numbering_resolution' => $this->numbering->activeResolutionFor($invoice->company, DocumentType::Invoice) !== null,
        ];
    }
}
