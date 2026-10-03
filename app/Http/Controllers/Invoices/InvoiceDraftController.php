<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Invoices\StoreInvoiceItemRequest;
use App\Http\Requests\Invoices\UpdateInvoiceCustomerRequest;
use App\Http\Requests\Invoices\UpdateInvoiceItemRequest;
use App\Http\Requests\Invoices\UpdateInvoiceSummaryRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductService;
use App\Models\Tax;
use App\Services\Invoices\InvoiceRecalculationService;
use App\Services\Invoices\InvoiceStateMachine;
use App\Services\Invoices\InvoiceTraceLogger;
use App\Services\Tax\NitDvCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceDraftController extends Controller
{
    public function __construct(
        private readonly InvoiceRecalculationService $recalculator,
        private readonly InvoiceTraceLogger $logger,
    ) {}

    /**
     * Entry point for "Nueva factura" (sidebar/dashboard CTAs already
     * link here by name) — creates a real draft and redirects into it.
     */
    public function create(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = new Invoice(['user_id' => $request->user()->id]);
        $invoice->save();

        $this->logger->log($invoice, 'draft_created', 'Se creó un nuevo borrador de factura.', null, InvoiceStatus::Draft);

        return redirect()->route('invoices.draft.customer', $invoice);
    }

    public function customer(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.drafts.customer', [
            'invoice' => $invoice,
            'customers' => Customer::orderBy('name')->get(),
            'finalConsumer' => Customer::where('is_final_consumer', true)->first(),
        ]);
    }

    public function updateCustomer(UpdateInvoiceCustomerRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $this->ensureEditable($invoice);

        $customer = Customer::find($request->validated('customer_id'));
        abort_unless($customer !== null, 404);

        $invoice->customer_id = $customer->id;
        $invoice->save();

        $this->logger->log($invoice, 'customer_selected', "Se seleccionó el cliente {$customer->name}.", $invoice->status, $invoice->status, ['customer_id' => $customer->id]);

        return redirect()->route('invoices.draft.customer', $invoice)->with('status', 'Cliente asignado correctamente.');
    }

    public function quickCreateCustomer(StoreCustomerRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $this->ensureEditable($invoice);

        $data = $request->validated();

        $customer = new Customer($data);
        $customer->dv = $data['identification_type'] === 'NIT'
            ? (string) NitDvCalculator::calculate($data['identification_number'])
            : null;
        $customer->save();

        $invoice->customer_id = $customer->id;
        $invoice->save();

        $this->logger->log($invoice, 'customer_selected', "Se creó y seleccionó el cliente {$customer->name}.", $invoice->status, $invoice->status, ['customer_id' => $customer->id, 'quick_created' => true]);

        return redirect()->route('invoices.draft.customer', $invoice)->with('status', 'Cliente creado y asignado correctamente.');
    }

    public function items(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $products = ProductService::query()->availableForInvoicing()->with('taxes')->orderBy('name')->get();

        return view('invoices.drafts.items', [
            'invoice' => $invoice,
            'items' => $invoice->items()->with('itemTaxes')->orderBy('id')->get(),
            'products' => $products,
            'productsJson' => $products->map(fn (ProductService $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'price' => (string) $product->price,
                'tax_ids' => $product->taxes->pluck('id')->all(),
            ])->values(),
            'taxes' => Tax::query()->currentlyValid()->availableFor($invoice->company_id)->get(),
        ]);
    }

    public function storeItem(StoreInvoiceItemRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $this->ensureEditable($invoice);

        $data = $request->validated();
        $product = isset($data['product_service_id']) ? ProductService::find($data['product_service_id']) : null;

        $item = new InvoiceItem([
            'product_service_id' => $product?->id,
            'description' => ($data['description'] ?? null) ?: $product?->name ?: 'Línea sin descripción',
            'unit' => ($data['unit'] ?? null) ?: $product?->unit ?: 'unidad',
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'discount_percent' => $data['discount_percent'] ?? 0,
        ]);
        $item->invoice_id = $invoice->id;
        $item->save();

        $taxIds = $data['tax_ids'] ?? $product?->taxes->pluck('id')->all() ?? [];
        $item->itemTaxes()->createMany(
            Tax::whereIn('id', $taxIds)->get()->map(fn (Tax $tax) => [
                'tax_id' => $tax->id,
                'code' => $tax->code ?? ('TAX-'.$tax->id),
                'name' => $tax->name,
                'base' => 0,
                'rate' => $tax->rate,
                'value' => 0,
            ])->all()
        );

        $this->recalculator->recalculate($invoice);
        $this->logger->log($invoice, 'item_added', "Se agregó la línea \"{$item->description}\".", $invoice->status, $invoice->status, ['item_id' => $item->id]);

        return redirect()->route('invoices.draft.items', $invoice)->with('status', 'Producto agregado correctamente.');
    }

    public function updateItem(UpdateInvoiceItemRequest $request, Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        abort_unless($item->invoice_id === $invoice->id, 404);
        $this->authorize('update', $invoice);
        $this->ensureEditable($invoice);

        $data = $request->validated();
        $product = isset($data['product_service_id']) ? ProductService::find($data['product_service_id']) : null;

        $item->forceFill([
            'product_service_id' => $product?->id,
            'description' => ($data['description'] ?? null) ?: $item->description,
            'unit' => ($data['unit'] ?? null) ?: $item->unit,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'discount_percent' => $data['discount_percent'] ?? 0,
        ])->save();

        $item->itemTaxes()->delete();
        $taxIds = $data['tax_ids'] ?? $product?->taxes->pluck('id')->all() ?? [];
        $item->itemTaxes()->createMany(
            Tax::whereIn('id', $taxIds)->get()->map(fn (Tax $tax) => [
                'tax_id' => $tax->id,
                'code' => $tax->code ?? ('TAX-'.$tax->id),
                'name' => $tax->name,
                'base' => 0,
                'rate' => $tax->rate,
                'value' => 0,
            ])->all()
        );

        $this->recalculator->recalculate($invoice);
        $this->logger->log($invoice, 'item_updated', "Se actualizó la línea \"{$item->description}\".", $invoice->status, $invoice->status, ['item_id' => $item->id, 'quantity' => $data['quantity']]);

        return redirect()->route('invoices.draft.items', $invoice)->with('status', 'Producto actualizado correctamente.');
    }

    public function destroyItem(Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        abort_unless($item->invoice_id === $invoice->id, 404);
        $this->authorize('update', $invoice);
        $this->ensureEditable($invoice);

        $description = $item->description;
        $item->delete();

        $this->recalculator->recalculate($invoice);
        $this->logger->log($invoice, 'item_removed', "Se eliminó la línea \"{$description}\".", $invoice->status, $invoice->status);

        return redirect()->route('invoices.draft.items', $invoice)->with('status', 'Producto eliminado correctamente.');
    }

    public function summary(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.drafts.summary', [
            'invoice' => $invoice->load('items.itemTaxes'),
        ]);
    }

    public function updateSummary(UpdateInvoiceSummaryRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $this->ensureEditable($invoice);

        $invoice->fill($request->validated());
        $invoice->save();

        return redirect()->route('invoices.draft.summary', $invoice)->with('status', 'Resumen actualizado correctamente.');
    }

    public function discard(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if (! InvoiceStateMachine::isEditable($invoice->status)) {
            abort(403, 'Esta factura ya no se puede descartar.');
        }

        $from = $invoice->status;
        InvoiceStateMachine::transition($invoice, InvoiceStatus::Discarded);
        $invoice->save();
        $this->logger->log($invoice, 'discarded', 'El borrador fue descartado.', $from, InvoiceStatus::Discarded);

        return redirect()->route('dashboard')->with('status', 'Borrador descartado.');
    }

    private function ensureEditable(Invoice $invoice): void
    {
        if (! InvoiceStateMachine::isEditable($invoice->status)) {
            abort(403, 'Esta factura ya no se puede editar.');
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            $from = $invoice->status;
            InvoiceStateMachine::transition($invoice, InvoiceStatus::Draft);
            $invoice->save();
            $this->logger->log($invoice, 'draft_reopened', 'La factura volvió a borrador porque se modificaron sus datos.', $from, InvoiceStatus::Draft);
        }
    }
}
