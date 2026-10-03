<x-layouts.app title="Notas crédito" active="credit-notes">
    <x-page-header eyebrow="Notas crédito" title="Notas crédito" description="Documentos de corrección simulados asociados a facturas emitidas.">
        <x-slot:actions>
            @can('create', \App\Models\CreditNote::class)
                <a href="{{ route('credit-notes.create') }}" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Crear nota crédito
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('credit-notes.index') }}" class="mb-lg grid grid-cols-1 md:grid-cols-[1fr_220px_auto] gap-sm">
        <label>
            <span class="sr-only">Buscar</span>
            <input name="search" value="{{ $search }}" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md" placeholder="Buscar por nota, factura, cliente o motivo">
        </label>
        <label>
            <span class="sr-only">Estado</span>
            <select name="status" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                <option value="">Todos los estados</option>
                @foreach (\App\Enums\CreditNoteStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </label>
        <button class="px-lg py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md flex items-center justify-center gap-sm" type="submit">
            <span class="material-symbols-outlined text-[18px]">search</span>
            Filtrar
        </button>
    </form>

    <x-ui.data-table :table="[
        'title' => 'Notas crédito registradas',
        'headers' => ['Nota', 'Factura origen', 'Cliente', 'Motivo', 'Total', 'Estado'],
        'hideViewAll' => true,
        'emptyMessage' => 'No hay notas crédito para mostrar.',
        'paginator' => $creditNotes,
        'rows' => $creditNotes->map(fn ($creditNote) => [
            'cells' => [
                $creditNote->number ?? 'Borrador #'.$creditNote->id,
                $creditNote->invoice->number ?? 'Sin número',
                $creditNote->invoice->customer->name ?? ($creditNote->invoice->customer_snapshot['name'] ?? '-'),
                $creditNote->reason,
                \App\Support\ReportFormatter::money($creditNote->total),
                $creditNote->status->label(),
            ],
            'actions' => array_values(array_filter([
                ['label' => 'Ver detalle', 'icon' => 'visibility', 'href' => route('credit-notes.show', $creditNote)],
                $creditNote->status === \App\Enums\CreditNoteStatus::Issued ? ['label' => 'Descargar PDF', 'icon' => 'download', 'href' => route('credit-notes.pdf.download', $creditNote)] : null,
                ['label' => 'Ver factura relacionada', 'icon' => 'receipt_long', 'href' => route('invoices.show', $creditNote->invoice)],
            ])),
        ])->all(),
    ]" />
</x-layouts.app>
