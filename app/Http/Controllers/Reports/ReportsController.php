<?php

namespace App\Http\Controllers\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Services\Reports\DocumentReportService;
use App\Services\Reports\ErrorReportService;
use App\Services\Reports\ProductivityReportService;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportQueryFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly DocumentReportService $documents,
        private readonly ProductivityReportService $productivity,
        private readonly ErrorReportService $errors,
        private readonly ReportQueryFactory $queries,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-reports');

        return response()->view('reports.index', $this->viewData($request, 'general'));
    }

    public function errors(Request $request): Response
    {
        Gate::authorize('view-reports');

        return response()->view('reports.index', $this->viewData($request, 'errors'));
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('view-reports');

        $filters = $this->validatedFilters($request);
        $range = ReportDateRange::fromFilters($filters);
        $rows = $this->documents->documents($request->user(), $filters, $range);
        $filename = 'reporte-documental-'.$range->start->format('Ymd').'-'.$range->end->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, ['Tipo', 'Número', 'Cliente', 'Usuario', 'Estado', 'Total', 'Fecha']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['type'],
                    $row['number'],
                    $row['customer'],
                    $row['user'],
                    $row['status'],
                    number_format((float) $row['total'], 2, '.', ''),
                    $row['date'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Request $request, string $activeSection): array
    {
        $filters = $this->validatedFilters($request);
        $range = ReportDateRange::fromFilters($filters);
        $user = $request->user();

        return [
            'range' => $range,
            'filters' => $filters,
            'filterOptions' => [
                'ranges' => ReportDateRange::OPTIONS,
                'statuses' => $this->queries->statusOptions(),
                'users' => $this->queries->usersForCompany($user),
                'customers' => $this->queries->customersForCompany($user),
                'documentTypes' => [
                    'all' => 'Todos',
                    'invoices' => 'Facturas',
                    'credit_notes' => 'Notas crédito',
                ],
            ],
            'documentReport' => $this->documents->dataFor($user, $filters, $range),
            'productivityReport' => $this->productivity->dataFor($user, $filters, $range),
            'errorReport' => $this->errors->dataFor($user, $filters, $range),
            'activeSection' => $activeSection,
            'isLimitedToOwnDocuments' => $this->queries->mustLimitToOwnDocuments($user),
            'canViewUserBreakdown' => $this->queries->canViewUserBreakdown($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $statusValues = array_values(array_unique(array_merge(
            array_map(fn (InvoiceStatus $status): string => $status->value, InvoiceStatus::cases()),
            array_map(fn (CreditNoteStatus $status): string => $status->value, CreditNoteStatus::cases()),
        )));

        $validated = $request->validate([
            'date_range' => ['nullable', Rule::in(array_keys(ReportDateRange::OPTIONS))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in($statusValues)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'document_type' => ['nullable', Rule::in(['all', 'invoices', 'credit_notes'])],
        ]);

        $validated['date_range'] ??= ReportDateRange::DEFAULT;
        $validated['document_type'] ??= 'all';

        return collect($validated)
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
    }
}
