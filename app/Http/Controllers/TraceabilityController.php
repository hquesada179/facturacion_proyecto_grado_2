<?php

namespace App\Http\Controllers;

use App\Models\InvoiceEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraceabilityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view-traceability');

        $term = $request->string('q')->toString() ?: null;

        $events = InvoiceEvent::query()
            // InvoiceEvent has no company_id of its own; the whereHas scopes
            // this to the authenticated user's company through Invoice's own
            // BelongsToCompany global scope.
            ->whereHas('invoice', function ($query) use ($term): void {
                if ($term !== null) {
                    $query->where('number', 'like', "%{$term}%");
                }
            })
            ->with(['invoice.customer', 'user'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('traceability.index', [
            'events' => $events,
            'filters' => $request->only(['q']),
        ]);
    }
}
