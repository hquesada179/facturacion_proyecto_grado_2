<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $term = $request->string('q')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;

        $invoices = Invoice::query()
            ->with('customer')
            ->when($term, function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->only(['q', 'status']),
            'statuses' => InvoiceStatus::cases(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['items.itemTaxes', 'customer', 'events.user']),
        ]);
    }
}
