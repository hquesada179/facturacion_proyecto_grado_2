<?php

namespace App\Services\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ReportFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentReportService
{
    public function __construct(private readonly ReportQueryFactory $queries) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dataFor(User $user, array $filters, ReportDateRange $range): array
    {
        $summary = $this->summary($user, $filters, $range);

        return [
            'summary' => $summary,
            'summaryCards' => $this->summaryCards($summary),
            'series' => $this->documentsByDay($user, $filters, $range),
            'byStatus' => $this->byStatus($user, $filters, $range),
            'byClients' => $this->byClients($user, $filters, $range),
            'byProducts' => $this->byProducts($user, $filters, $range),
            'byUsers' => $this->byUsers($user, $filters, $range),
            'documents' => $this->documents($user, $filters, $range, 25),
            'canViewUserBreakdown' => $this->queries->canViewUserBreakdown($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(User $user, array $filters, ReportDateRange $range): array
    {
        $includeInvoices = $this->queries->includeInvoices($filters);
        $includeCreditNotes = $this->queries->includeCreditNotes($filters);

        $created = $includeInvoices
            ? (clone $this->queries->invoices($user, $filters, $range))->count()
            : 0;

        $issuedStatuses = [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyCredited->value,
            InvoiceStatus::Voided->value,
        ];

        $emitted = $includeInvoices
            ? (clone $this->queries->invoices($user, $filters, $range, 'issued_at'))
                ->whereIn('status', $issuedStatuses)
                ->count()
            : 0;

        $rejected = $includeInvoices
            ? (clone $this->queries->invoices($user, $filters, $range))
                ->whereIn('status', [InvoiceStatus::SimulatedRejected->value, InvoiceStatus::TechnicalError->value])
                ->count()
            : 0;

        $voided = $includeInvoices
            ? (clone $this->queries->invoices($user, $filters, $range))
                ->where('status', InvoiceStatus::Voided->value)
                ->count()
            : 0;

        $creditNoteQuery = $this->queries->creditNotes($user, $filters, $range, 'issued_at')
            ->where('status', CreditNoteStatus::Issued->value);

        $creditNotes = $includeCreditNotes ? (clone $creditNoteQuery)->count() : 0;

        $financialInvoiceQuery = $this->queries->invoices($user, $filters, $range, 'issued_at')
            ->whereIn('status', $issuedStatuses);

        $gross = $includeInvoices ? (float) (clone $financialInvoiceQuery)->sum('subtotal') : 0.0;
        $taxTotal = $includeInvoices ? (float) (clone $financialInvoiceQuery)->sum('tax_total') : 0.0;
        $invoiceTotal = $includeInvoices ? (float) (clone $financialInvoiceQuery)->sum('total') : 0.0;
        $credited = $includeCreditNotes ? (float) (clone $creditNoteQuery)->sum('total') : 0.0;

        return [
            'created' => $created,
            'emitted' => $emitted,
            'rejected' => $rejected,
            'voided' => $voided,
            'credit_notes' => $creditNotes,
            'gross' => $gross,
            'tax_total' => $taxTotal,
            'credited' => $credited,
            'net' => $invoiceTotal - $credited,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, array<string, string>>
     */
    public function summaryCards(array $summary): array
    {
        return [
            ['label' => 'Facturas creadas', 'value' => ReportFormatter::integer($summary['created']), 'icon' => 'note_add', 'tone' => 'primary'],
            ['label' => 'Facturas emitidas', 'value' => ReportFormatter::integer($summary['emitted']), 'icon' => 'receipt_long', 'tone' => 'secondary'],
            ['label' => 'Rechazadas o con error', 'value' => ReportFormatter::integer($summary['rejected']), 'icon' => 'error', 'tone' => 'error'],
            ['label' => 'Facturas anuladas', 'value' => ReportFormatter::integer($summary['voided']), 'icon' => 'block', 'tone' => 'warning'],
            ['label' => 'Notas crédito', 'value' => ReportFormatter::integer($summary['credit_notes']), 'icon' => 'assignment_return', 'tone' => 'secondary'],
            ['label' => 'Valor bruto facturado', 'value' => ReportFormatter::money($summary['gross']), 'icon' => 'payments', 'tone' => 'primary'],
            ['label' => 'Impuesto total', 'value' => ReportFormatter::money($summary['tax_total']), 'icon' => 'percent', 'tone' => 'secondary'],
            ['label' => 'Valor acreditado', 'value' => ReportFormatter::money($summary['credited']), 'icon' => 'undo', 'tone' => 'warning'],
            ['label' => 'Neto documental', 'value' => ReportFormatter::money($summary['net']), 'icon' => 'account_balance', 'tone' => 'primary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{label: string, count: int, height: int}>
     */
    public function documentsByDay(User $user, array $filters, ReportDateRange $range): Collection
    {
        $counts = collect();

        if ($this->queries->includeInvoices($filters)) {
            $this->queries->invoices($user, $filters, $range)
                ->get(['created_at'])
                ->each(function (Invoice $invoice) use ($counts): void {
                    $key = $invoice->created_at?->format('Y-m-d');
                    $counts[$key] = (int) ($counts[$key] ?? 0) + 1;
                });
        }

        if ($this->queries->includeCreditNotes($filters)) {
            $this->queries->creditNotes($user, $filters, $range)
                ->get(['created_at'])
                ->each(function (CreditNote $creditNote) use ($counts): void {
                    $key = $creditNote->created_at?->format('Y-m-d');
                    $counts[$key] = (int) ($counts[$key] ?? 0) + 1;
                });
        }

        $max = max(1, (int) $counts->max());

        return $counts
            ->sortKeys()
            ->map(fn (int $count, string $date): array => [
                'label' => $this->shortDateLabel($date),
                'count' => $count,
                'height' => max(8, (int) round(($count / $max) * 100)),
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, string|int|float>>
     */
    public function byStatus(User $user, array $filters, ReportDateRange $range): Collection
    {
        $rows = collect();

        if ($this->queries->includeInvoices($filters)) {
            $invoiceRows = $this->queries->invoices($user, $filters, $range)
                ->select('status')
                ->selectRaw('COUNT(*) as documents')
                ->groupBy('status')
                ->orderByDesc('documents')
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'document_type' => 'Factura',
                    'status' => $invoice->status->label(),
                    'documents' => (int) $invoice->documents,
                ]);

            $rows = $rows->concat($invoiceRows);
        }

        if ($this->queries->includeCreditNotes($filters)) {
            $creditRows = $this->queries->creditNotes($user, $filters, $range)
                ->select('status')
                ->selectRaw('COUNT(*) as documents')
                ->groupBy('status')
                ->orderByDesc('documents')
                ->get()
                ->map(fn (CreditNote $creditNote): array => [
                    'document_type' => 'Nota crédito',
                    'status' => $creditNote->status->label(),
                    'documents' => (int) $creditNote->documents,
                ]);

            $rows = $rows->concat($creditRows);
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function byClients(User $user, array $filters, ReportDateRange $range): Collection
    {
        $clients = collect();

        if ($this->queries->includeInvoices($filters)) {
            $invoiceQuery = $this->queries->invoices($user, $filters, $range)
                ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
                ->selectRaw("customers.id as customer_id, COALESCE(customers.name, 'Cliente sin asociar') as customer_name")
                ->selectRaw('COUNT(invoices.id) as invoices_count')
                ->selectRaw('SUM(invoices.total) as invoiced_total')
                ->groupBy('customers.id', 'customers.name');

            $invoiceQuery->get()->each(function (object $row) use ($clients): void {
                $clients[(int) ($row->customer_id ?? 0)] = [
                    'customer' => $row->customer_name,
                    'invoices' => (int) $row->invoices_count,
                    'credit_notes' => 0,
                    'invoiced_total' => (float) $row->invoiced_total,
                    'credited_total' => 0.0,
                ];
            });
        }

        if ($this->queries->includeCreditNotes($filters)) {
            $creditQuery = $this->queries->creditNotes($user, $filters, $range)
                ->join('invoices as credited_invoices', 'credited_invoices.id', '=', 'credit_notes.invoice_id')
                ->leftJoin('customers', 'customers.id', '=', 'credited_invoices.customer_id')
                ->selectRaw("customers.id as customer_id, COALESCE(customers.name, 'Cliente sin asociar') as customer_name")
                ->selectRaw('COUNT(credit_notes.id) as credit_notes_count')
                ->selectRaw('SUM(credit_notes.total) as credited_total')
                ->groupBy('customers.id', 'customers.name');

            $creditQuery->get()->each(function (object $row) use ($clients): void {
                $key = (int) ($row->customer_id ?? 0);
                $existing = $clients[$key] ?? [
                    'customer' => $row->customer_name,
                    'invoices' => 0,
                    'credit_notes' => 0,
                    'invoiced_total' => 0.0,
                    'credited_total' => 0.0,
                ];

                $existing['credit_notes'] = (int) $row->credit_notes_count;
                $existing['credited_total'] = (float) $row->credited_total;
                $clients[$key] = $existing;
            });
        }

        return $clients
            ->values()
            ->sortByDesc(fn (array $row): float => $row['invoiced_total'] - $row['credited_total'])
            ->take(10)
            ->values()
            ->map(fn (array $row): array => $row + [
                'net_total' => $row['invoiced_total'] - $row['credited_total'],
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function byProducts(User $user, array $filters, ReportDateRange $range): Collection
    {
        $products = collect();

        if ($this->queries->includeInvoices($filters)) {
            $query = DB::table('invoice_items')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id');

            $this->queries->applyInvoiceFilters($query, $user, $filters, $range);

            $query
                ->selectRaw("COALESCE(invoice_items.product_code, '') as product_code")
                ->selectRaw('invoice_items.description as description')
                ->selectRaw('SUM(invoice_items.quantity) as quantity')
                ->selectRaw('SUM(invoice_items.line_total) as invoiced_total')
                ->groupBy('invoice_items.product_code', 'invoice_items.description')
                ->orderByDesc('invoiced_total')
                ->get()
                ->each(function (object $row) use ($products): void {
                    $key = trim(($row->product_code ?: '').'|'.$row->description);
                    $products[$key] = [
                        'code' => $row->product_code ?: 'Sin código',
                        'description' => $row->description,
                        'quantity' => (float) $row->quantity,
                        'invoiced_total' => (float) $row->invoiced_total,
                        'credited_total' => 0.0,
                    ];
                });
        }

        if ($this->queries->includeCreditNotes($filters)) {
            $query = DB::table('credit_note_items')
                ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
                ->join('invoices as credited_invoices', 'credited_invoices.id', '=', 'credit_notes.invoice_id');

            $this->queries->applyCreditNoteFilters($query, $user, $filters, $range, 'created_at', 'credit_notes', 'credited_invoices');

            $query
                ->selectRaw("COALESCE(credit_note_items.product_code, '') as product_code")
                ->selectRaw('credit_note_items.description as description')
                ->selectRaw('SUM(credit_note_items.line_total) as credited_total')
                ->groupBy('credit_note_items.product_code', 'credit_note_items.description')
                ->get()
                ->each(function (object $row) use ($products): void {
                    $key = trim(($row->product_code ?: '').'|'.$row->description);
                    $existing = $products[$key] ?? [
                        'code' => $row->product_code ?: 'Sin código',
                        'description' => $row->description,
                        'quantity' => 0.0,
                        'invoiced_total' => 0.0,
                        'credited_total' => 0.0,
                    ];

                    $existing['credited_total'] = (float) $row->credited_total;
                    $products[$key] = $existing;
                });
        }

        return $products
            ->values()
            ->sortByDesc(fn (array $row): float => $row['invoiced_total'] - $row['credited_total'])
            ->take(10)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function byUsers(User $user, array $filters, ReportDateRange $range): Collection
    {
        if (! $this->queries->canViewUserBreakdown($user)) {
            return collect();
        }

        $users = collect();

        if ($this->queries->includeInvoices($filters)) {
            $query = $this->queries->invoices($user, $filters, $range)
                ->leftJoin('users', 'users.id', '=', 'invoices.user_id')
                ->selectRaw("users.id as user_id, COALESCE(users.name, 'Sin usuario') as user_name")
                ->selectRaw('COUNT(invoices.id) as invoices_count')
                ->selectRaw('SUM(CASE WHEN invoices.status IN (?, ?, ?) THEN 1 ELSE 0 END) as emitted_count', [
                    InvoiceStatus::Issued->value,
                    InvoiceStatus::PartiallyCredited->value,
                    InvoiceStatus::Voided->value,
                ])
                ->selectRaw('SUM(invoices.total) as invoiced_total')
                ->groupBy('users.id', 'users.name');

            $query->get()->each(function (object $row) use ($users): void {
                $users[(int) ($row->user_id ?? 0)] = [
                    'user' => $row->user_name,
                    'invoices' => (int) $row->invoices_count,
                    'emitted' => (int) $row->emitted_count,
                    'credit_notes' => 0,
                    'total' => (float) $row->invoiced_total,
                ];
            });
        }

        if ($this->queries->includeCreditNotes($filters)) {
            $query = $this->queries->creditNotes($user, $filters, $range)
                ->leftJoin('users', 'users.id', '=', 'credit_notes.user_id')
                ->selectRaw("users.id as user_id, COALESCE(users.name, 'Sin usuario') as user_name")
                ->selectRaw('COUNT(credit_notes.id) as credit_notes_count')
                ->groupBy('users.id', 'users.name');

            $query->get()->each(function (object $row) use ($users): void {
                $key = (int) ($row->user_id ?? 0);
                $existing = $users[$key] ?? [
                    'user' => $row->user_name,
                    'invoices' => 0,
                    'emitted' => 0,
                    'credit_notes' => 0,
                    'total' => 0.0,
                ];

                $existing['credit_notes'] = (int) $row->credit_notes_count;
                $users[$key] = $existing;
            });
        }

        return $users
            ->values()
            ->sortByDesc('emitted')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function documents(User $user, array $filters, ReportDateRange $range, ?int $limit = null): Collection
    {
        $rows = collect();

        if ($this->queries->includeInvoices($filters)) {
            $rows = $rows->concat(
                $this->queries->invoices($user, $filters, $range)
                    ->with('customer', 'user')
                    ->latest('created_at')
                    ->get()
                    ->map(fn (Invoice $invoice): array => [
                        'type' => 'Factura',
                        'number' => $invoice->number ?: 'Borrador #'.$invoice->id,
                        'customer' => $invoice->customer?->name ?? 'Cliente sin asociar',
                        'user' => $invoice->user?->name ?? 'Sin usuario',
                        'status' => $invoice->status->label(),
                        'total' => (float) $invoice->total,
                        'formatted_total' => ReportFormatter::money($invoice->total),
                        'document_date' => $invoice->created_at,
                        'date' => $invoice->created_at?->format('d/m/Y H:i'),
                        'href' => route('invoices.show', $invoice),
                    ])
            );
        }

        if ($this->queries->includeCreditNotes($filters)) {
            $rows = $rows->concat(
                $this->queries->creditNotes($user, $filters, $range)
                    ->with('invoice.customer', 'user')
                    ->latest('created_at')
                    ->get()
                    ->map(fn (CreditNote $creditNote): array => [
                        'type' => 'Nota crédito',
                        'number' => $creditNote->number ?: 'Borrador NC #'.$creditNote->id,
                        'customer' => $creditNote->invoice?->customer?->name ?? 'Cliente sin asociar',
                        'user' => $creditNote->user?->name ?? 'Sin usuario',
                        'status' => $creditNote->status->label(),
                        'total' => (float) $creditNote->total,
                        'formatted_total' => ReportFormatter::money($creditNote->total),
                        'document_date' => $creditNote->created_at,
                        'date' => $creditNote->created_at?->format('d/m/Y H:i'),
                        'href' => route('credit-notes.show', $creditNote),
                    ])
            );
        }

        $rows = $rows->sortByDesc('document_date')->values();

        return $limit ? $rows->take($limit)->values() : $rows;
    }

    private function shortDateLabel(string $date): string
    {
        return CarbonImmutable::parse($date)->format('d/m');
    }
}
