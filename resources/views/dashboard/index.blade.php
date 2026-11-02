@php
    // Real, per-request greeting — never a stored/cached value — derived
    // from the authenticated user already available in every Blade view
    // (same auth()->user() the sidebar's own footer already renders).
    $greetingHour = (int) now()->format('G');
    $greeting = match (true) {
        $greetingHour < 12 => 'Buenos días',
        $greetingHour < 19 => 'Buenas tardes',
        default => 'Buenas noches',
    };
    $greetingFirstName = strtok(auth()->user()->name, ' ') ?: auth()->user()->name;
@endphp

<x-layouts.app title="Dashboard" active="dashboard" wide>
    <div data-tour-target="dashboard-main">
    <x-page-header
        eyebrow="Inicio"
        title="{{ $greeting }}, {{ $greetingFirstName }}"
        description="Este es el estado operativo de Fiscora hoy."
    >
        <x-slot:actions>
            @can('manage-invoicing')
                <a href="{{ route('invoices.create.customer') }}" data-tour-target="new-invoice" class="bg-primary text-on-primary rounded-lg px-lg py-sm flex items-center gap-sm font-label-md text-label-md font-semibold shadow-hover">
                    <span class="material-symbols-outlined text-[17px]">add_circle</span>
                    Nueva factura
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($isLimitedToOwnDocuments)
        <div class="mb-lg rounded-lg border border-outline-variant bg-surface-container-lowest p-md text-on-surface-variant font-body-sm text-body-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-primary text-[20px]">info</span>
            Estás viendo únicamente los documentos asociados a tu usuario facturador.
        </div>
    @endif

    <x-onboarding-checklist :checklist="$checklist" />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-sm mb-lg">
        @foreach ($metrics as $metric)
            <div>
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" :featured="$loop->first" />
                @if (! empty($metric['hint']))
                    <p class="mt-xs px-xs font-label-sm text-label-sm text-on-surface-variant">{{ $metric['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mb-lg">
        <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Accesos rápidos</p>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-sm">
            @can('manage-invoicing')
                <a href="{{ route('invoices.create.customer') }}" class="surface-card surface-card-interactive bg-primary border-primary text-on-primary font-label-md text-label-md font-semibold py-sm px-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[19px]">add_circle</span>
                    Nueva factura
                </a>
                <a href="{{ route('customers.create') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md font-semibold py-sm px-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[19px] text-primary">person_add</span>
                    Nuevo cliente
                </a>
                <a href="{{ route('products.create') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md font-semibold py-sm px-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[19px] text-primary">add_box</span>
                    Nuevo producto
                </a>
            @endcan
            <a href="{{ route('reports.index') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md font-semibold py-sm px-md flex items-center gap-sm">
                <span class="material-symbols-outlined text-[19px] text-primary">analytics</span>
                Ver reportes
            </a>
            @can('view-traceability')
                <a href="{{ route('traceability.index') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md font-semibold py-sm px-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[19px] text-primary">history</span>
                    Trazabilidad
                </a>
            @endcan
        </div>
    </div>

    <x-ui.data-table :table="[
        'title' => 'Documentos recientes',
        'description' => 'Facturas y notas crédito ordenadas por actividad reciente.',
        'headers' => ['Tipo', 'Número', 'Cliente', 'Estado', 'Total', 'Fecha'],
        'viewAllHref' => route('reports.index'),
        'viewAllLabel' => 'Ver indicadores',
        'emptyMessage' => 'Aún no hay facturas ni notas crédito reales para mostrar.',
        'rows' => $recentDocuments->map(fn ($document) => [
            'cells' => [
                $document['type'],
                $document['number'],
                $document['customer'],
                $document['status'],
                $document['total'],
                $document['date'],
            ],
            'actions' => [
                ['label' => 'Ver documento', 'icon' => $document['icon'], 'href' => $document['href'], 'method' => 'GET'],
            ],
        ])->all(),
    ]" />
    </div>
</x-layouts.app>
