<?php

namespace App\Http\Controllers\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CreditNotes\CreditNoteDraftService;
use App\Services\CreditNotes\CreditNoteStateMachine;
use App\Services\CreditNotes\CreditNoteValidationService;
use App\Services\CreditNotes\IssueCreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteDraftService $draftService,
        private readonly CreditNoteValidationService $validationService,
        private readonly IssueCreditNoteService $issueService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditNote::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $creditNotes = CreditNote::query()
            ->with(['invoice.customer'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('invoice.customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('credit-notes.index', [
            'creditNotes' => $creditNotes,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CreditNote::class);

        $invoice = null;
        if ($request->filled('invoice_id')) {
            $invoice = Invoice::query()
                ->with(['customer', 'items.itemTaxes'])
                ->whereKey($request->integer('invoice_id'))
                ->firstOrFail();

            if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyCredited], true)) {
                $invoice = null;
            }
        }

        $invoices = Invoice::query()
            ->with('customer')
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyCredited->value])
            ->latest('issued_at')
            ->limit(50)
            ->get();

        return view('credit-notes.create', [
            'invoice' => $invoice?->loadMissing(['items.itemTaxes']),
            'invoices' => $invoices,
            'reasons' => CreditNote::REASONS,
            'availableByItem' => $invoice
                ? $invoice->items->mapWithKeys(fn ($item) => [$item->id => $this->validationService->availableQuantity($item)])->all()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CreditNote::class);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'reason_code' => ['required', Rule::in(array_keys(CreditNote::REASONS))],
            'reason_text' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = Invoice::query()->with(['items.itemTaxes'])->findOrFail($data['invoice_id']);
        $creditNote = $this->draftService->create($request->user(), $invoice, $data);

        return redirect()->route('credit-notes.review', $creditNote)
            ->with('status', 'Borrador de nota crédito creado.');
    }

    public function review(CreditNote $creditNote): View
    {
        $this->authorize('view', $creditNote);

        $creditNote->load(['invoice.customer', 'items.invoiceItem', 'invoice.events.user']);

        return view('credit-notes.review', [
            'creditNote' => $creditNote,
            'validationErrors' => $this->validationService->validateDraft($creditNote),
        ]);
    }

    public function validateDraft(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        $creditNote = $this->issueService->validate($creditNote);

        return redirect()->route('credit-notes.review', $creditNote)
            ->with('status', 'Nota crédito validada localmente.');
    }

    public function issue(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('issue', $creditNote);

        $creditNote = $this->issueService->issue($creditNote);

        return match ($creditNote->status) {
            CreditNoteStatus::Issued => redirect()->route('credit-notes.show', $creditNote)
                ->with('status', "Nota crédito {$creditNote->number} emitida correctamente (simulado)."),
            CreditNoteStatus::SimulatedRejected => redirect()->route('credit-notes.review', $creditNote)
                ->with('error', 'La validación simulada fue rechazada: '.$creditNote->dian_simulation_message),
            CreditNoteStatus::TechnicalError => redirect()->route('credit-notes.review', $creditNote)
                ->with('error', 'Error técnico simulado: '.$creditNote->dian_simulation_message),
            default => redirect()->route('credit-notes.review', $creditNote),
        };
    }

    public function discard(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        if (! CreditNoteStateMachine::isEditable($creditNote->status)) {
            abort(403, 'Esta nota crédito ya no se puede descartar.');
        }

        CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::Discarded);
        $creditNote->save();

        return redirect()->route('credit-notes.index')->with('status', 'Nota crédito descartada.');
    }

    public function show(CreditNote $creditNote): View
    {
        $this->authorize('view', $creditNote);

        return view('credit-notes.show', [
            'creditNote' => $creditNote->load(['invoice.customer', 'items', 'invoice.events.user']),
        ]);
    }
}
