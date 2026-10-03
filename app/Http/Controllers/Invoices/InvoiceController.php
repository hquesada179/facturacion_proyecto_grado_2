<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['items.itemTaxes', 'customer', 'events.user']),
        ]);
    }
}
