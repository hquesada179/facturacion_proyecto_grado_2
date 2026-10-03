<?php

namespace App\Services\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportQueryFactory
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public function invoices(User $user, array $filters, ReportDateRange $range, string $dateColumn = 'created_at'): Builder
    {
        /** @var Builder<Invoice> $query */
        $query = Invoice::query()->withoutGlobalScopes();

        $this->applyInvoiceFilters($query, $user, $filters, $range, $dateColumn);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CreditNote>
     */
    public function creditNotes(User $user, array $filters, ReportDateRange $range, string $dateColumn = 'created_at'): Builder
    {
        /** @var Builder<CreditNote> $query */
        $query = CreditNote::query()->withoutGlobalScopes();

        $this->applyCreditNoteFilters($query, $user, $filters, $range, $dateColumn);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyInvoiceFilters(mixed $query, User $user, array $filters, ReportDateRange $range, string $dateColumn = 'created_at', string $table = 'invoices'): void
    {
        $query->where($table.'.company_id', $user->company_id)
            ->whereBetween($table.'.'.$dateColumn, [$range->start, $range->end]);

        if (! empty($filters['status']) && $this->isInvoiceStatus((string) $filters['status'])) {
            $query->where($table.'.status', (string) $filters['status']);
        }

        if ($this->mustLimitToOwnDocuments($user)) {
            $query->where($table.'.user_id', $user->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where($table.'.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where($table.'.customer_id', (int) $filters['customer_id']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyCreditNoteFilters(mixed $query, User $user, array $filters, ReportDateRange $range, string $dateColumn = 'created_at', string $table = 'credit_notes', string $invoiceTable = 'invoices'): void
    {
        $query->where($table.'.company_id', $user->company_id)
            ->whereBetween($table.'.'.$dateColumn, [$range->start, $range->end]);

        if (! empty($filters['status']) && $this->isCreditNoteStatus((string) $filters['status'])) {
            $query->where($table.'.status', (string) $filters['status']);
        }

        if ($this->mustLimitToOwnDocuments($user)) {
            $query->where($table.'.user_id', $user->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where($table.'.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['customer_id']) && method_exists($query, 'whereHas')) {
            $query->whereHas('invoice', function (Builder $invoiceQuery) use ($filters): void {
                $invoiceQuery->withoutGlobalScopes()
                    ->where('customer_id', (int) $filters['customer_id']);
            });
        } elseif (! empty($filters['customer_id'])) {
            $query->where($invoiceTable.'.customer_id', (int) $filters['customer_id']);
        }
    }

    public function mustLimitToOwnDocuments(User $user): bool
    {
        return $user->role === UserRole::Facturador;
    }

    public function canViewUserBreakdown(User $user): bool
    {
        return $user->can('view-user-report');
    }

    public function includeInvoices(array $filters): bool
    {
        return in_array($filters['document_type'] ?? 'all', ['all', 'invoices'], true);
    }

    public function includeCreditNotes(array $filters): bool
    {
        return in_array($filters['document_type'] ?? 'all', ['all', 'credit_notes'], true);
    }

    /**
     * @return Collection<int, User>
     */
    public function usersForCompany(User $user): Collection
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /**
     * @return Collection<int, Customer>
     */
    public function customersForCompany(User $user): Collection
    {
        return Customer::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'identification_number']);
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        $invoiceStatuses = collect(InvoiceStatus::cases())
            ->mapWithKeys(fn (InvoiceStatus $status): array => [$status->value => $status->label()]);

        $creditNoteStatuses = collect(CreditNoteStatus::cases())
            ->mapWithKeys(fn (CreditNoteStatus $status): array => [$status->value => $status->label()]);

        return $invoiceStatuses->merge($creditNoteStatuses)->all();
    }

    private function isInvoiceStatus(string $status): bool
    {
        return InvoiceStatus::tryFrom($status) !== null;
    }

    private function isCreditNoteStatus(string $status): bool
    {
        return CreditNoteStatus::tryFrom($status) !== null;
    }
}
