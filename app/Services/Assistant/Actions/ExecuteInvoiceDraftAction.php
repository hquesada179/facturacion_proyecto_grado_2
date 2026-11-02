<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantActionProposal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\Invoices\InvoiceRecalculationService;
use App\Services\Invoices\InvoiceTraceLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Confirm-time execution for `create_invoice_draft`. Re-validates
 * everything from scratch against the CURRENT database state — the
 * claimed AssistantActionProposal's payload only ever supplies WHICH
 * customer/products/quantities were requested, never a price or tax
 * rate to trust. If anything material changed since the preview was
 * shown (price, tax, customer/product no longer eligible), this throws
 * instead of silently using the stale numbers — see IA 2.0-D report,
 * "Revalidación al confirmar".
 */
class ExecuteInvoiceDraftAction
{
    public function __construct(
        private readonly InvoiceRecalculationService $recalculator,
        private readonly InvoiceTraceLogger $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, AssistantActionProposal $proposal): array
    {
        if (Gate::forUser($user)->denies('create', Invoice::class)) {
            throw ValidationException::withMessages(['action' => 'Tu rol no tiene permiso para crear facturas.']);
        }

        $payload = $proposal->payload;

        $customer = Customer::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey($payload['customer_id'] ?? null)
            ->first();

        if ($customer === null || $customer->status !== 'active' || Gate::forUser($user)->denies('view', $customer)) {
            throw ValidationException::withMessages([
                'customer' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
            ]);
        }

        $freshLines = [];

        foreach ($payload['lines'] ?? [] as $line) {
            $product = ProductService::withoutGlobalScopes()
                ->with('taxes')
                ->where('company_id', $user->company_id)
                ->whereKey($line['product_service_id'] ?? null)
                ->first();

            if ($product === null || Gate::forUser($user)->denies('view', $product)) {
                throw ValidationException::withMessages([
                    'lines' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
                ]);
            }

            $freshTaxIds = $product->taxes->pluck('id')->sort()->values()->all();
            $proposedTaxIds = $line['proposed_tax_ids'] ?? [];
            sort($proposedTaxIds);

            if ((string) $product->price !== (string) ($line['proposed_unit_price'] ?? null) || $freshTaxIds !== $proposedTaxIds) {
                throw ValidationException::withMessages([
                    'lines' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
                ]);
            }

            $freshLines[] = ['product' => $product, 'quantity' => $line['quantity'], 'taxes' => $product->taxes];
        }

        if ($freshLines === []) {
            throw ValidationException::withMessages(['lines' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.']);
        }

        $invoice = DB::transaction(function () use ($user, $customer, $freshLines, $payload, $proposal): Invoice {
            $invoice = new Invoice(array_filter([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'payment_type' => $payload['payment_type'] ?? null,
                'payment_method' => $payload['payment_method'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]));
            $invoice->company_id = $user->company_id;
            $invoice->save();

            foreach ($freshLines as $line) {
                $item = new InvoiceItem([
                    'product_service_id' => $line['product']->id,
                    'product_code' => $line['product']->sku,
                    'description' => $line['product']->name,
                    'unit' => $line['product']->unit,
                    'quantity' => $line['quantity'],
                    'unit_price' => (string) $line['product']->price,
                    'discount_percent' => 0,
                ]);
                $item->invoice_id = $invoice->id;
                $item->save();

                $item->itemTaxes()->createMany(
                    $line['taxes']->map(fn (Tax $tax): array => [
                        'tax_id' => $tax->id,
                        'code' => $tax->code ?? ('TAX-'.$tax->id),
                        'name' => $tax->name,
                        'base' => 0,
                        'rate' => $tax->rate,
                        'value' => 0,
                    ])->all()
                );
            }

            $this->recalculator->recalculate($invoice);
            $this->logger->log(
                $invoice,
                'draft_created',
                'Se preparó un borrador de factura desde el asistente, tras confirmación humana.',
                null,
                $invoice->status,
                ['source' => 'assistant', 'assistant_action_proposal_id' => $proposal->id],
            );

            return $invoice->refresh();
        });

        return [
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'customer' => $customer->name,
            'total' => (float) $invoice->total,
        ];
    }
}
