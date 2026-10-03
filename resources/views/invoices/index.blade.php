<x-layouts.app title="Facturas" active="invoices" screen="invoices.index">
    <x-page-header eyebrow="Facturas" title="Facturas" description="Consulta documentos emitidos, borradores y validaciones reales.">
        <x-slot:actions>
            @can('manage-invoicing')
                <a href="{{ route('invoices.create.customer') }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Nueva factura
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg flex flex-col md:flex-row gap-md md:items-center md:justify-between">
        <form method="GET" action="{{ route('invoices.index') }}" class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10" placeholder="Buscar por número o cliente" type="search">
        </form>
        <form method="GET" action="{{ route('invoices.index') }}" class="flex gap-sm">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <x-ui.data-table :table="[
        'title' => 'Documentos',
        'headers' => ['N° factura', 'Cliente', 'Fecha', 'Valor total', 'Estado'],
        'hideViewAll' => true,
        'emptyMessage' => 'No hay facturas registradas.',
        'paginator' => $invoices,
        'rows' => $invoices->map(fn ($invoice) => [
            'cells' => [
                $invoice->number ?? 'Borrador #'.$invoice->id,
                $invoice->customer?->name ?? 'Sin cliente',
                optional($invoice->issue_date)->format('Y-m-d') ?? '-',
                \App\Support\ReportFormatter::money($invoice->total),
                $invoice->status->label(),
            ],
            'actions' => [
                ['label' => 'Ver', 'icon' => 'visibility', 'href' => route('invoices.show', $invoice), 'method' => 'GET'],
            ],
        ])->all(),
    ]" />
</x-layouts.app>
