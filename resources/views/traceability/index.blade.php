<x-layouts.app title="Trazabilidad" active="traceability" screen="traceability.index">
    <x-page-header eyebrow="Trazabilidad" title="Historial documental" description="Eventos append-only registrados sobre las facturas de tu empresa." />

    <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg">
        <form method="GET" action="{{ route('traceability.index') }}" class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10" placeholder="Buscar por número de factura" type="search">
        </form>
    </div>

    <x-ui.data-table :table="[
        'title' => 'Eventos documentales',
        'headers' => ['Fecha', 'Documento', 'Usuario', 'Evento', 'Transición'],
        'hideViewAll' => true,
        'emptyMessage' => 'No hay eventos de trazabilidad registrados.',
        'paginator' => $events,
        'rows' => $events->map(fn ($event) => [
            'cells' => [
                $event->created_at->format('Y-m-d H:i'),
                $event->invoice->number ?? 'Borrador #'.$event->invoice_id,
                $event->user?->name ?? 'Sistema',
                $event->description,
                ($event->from_status ?? 'n/a').' -> '.($event->to_status ?? 'n/a'),
            ],
            'actions' => [
                ['label' => 'Ver factura', 'icon' => 'visibility', 'href' => route('invoices.show', $event->invoice_id), 'method' => 'GET'],
            ],
        ])->all(),
    ]" />
</x-layouts.app>
