<x-layouts.app title="Dashboard" active="dashboard">
    <x-page-header
        eyebrow="Inicio"
        title="Dashboard operativo"
        description="Indicadores reales del prototipo para la compañía autenticada."
    >
        <x-slot:actions>
            @can('manage-invoicing')
                <a href="{{ route('invoices.create.customer') }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
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

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-md mb-xl">
        @foreach ($metrics as $metric)
            <div>
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
                @if (! empty($metric['hint']))
                    <p class="mt-xs px-xs font-label-sm text-label-sm text-on-surface-variant">{{ $metric['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mb-lg">
        <h3 class="font-label-md text-label-md text-on-surface-variant mb-sm">Accesos rápidos</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-md">
            @can('manage-invoicing')
                <a href="{{ route('invoices.create.customer') }}" class="surface-card surface-card-interactive bg-primary border-primary text-on-primary font-label-md text-label-md py-md px-md flex flex-col items-center justify-center gap-sm h-28 text-center">
                    <span class="material-symbols-outlined text-[26px]">add_circle</span>
                    Nueva factura
                </a>
                <a href="{{ route('customers.create') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md py-md px-md flex flex-col items-center justify-center gap-sm h-28 text-center">
                    <span class="material-symbols-outlined text-[26px] text-primary">person_add</span>
                    Nuevo cliente
                </a>
                <a href="{{ route('products.create') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md py-md px-md flex flex-col items-center justify-center gap-sm h-28 text-center">
                    <span class="material-symbols-outlined text-[26px] text-primary">add_box</span>
                    Nuevo producto
                </a>
            @endcan
            <a href="{{ route('reports.index') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md py-md px-md flex flex-col items-center justify-center gap-sm h-28 text-center">
                <span class="material-symbols-outlined text-[26px] text-primary">analytics</span>
                Ver reportes
            </a>
            @can('view-traceability')
                <a href="{{ route('traceability.index') }}" class="surface-card surface-card-interactive text-on-surface hover:border-primary hover:text-primary font-label-md text-label-md py-md px-md flex flex-col items-center justify-center gap-sm h-28 text-center">
                    <span class="material-symbols-outlined text-[26px] text-primary">history</span>
                    Trazabilidad
                </a>
            @endcan
        </div>
    </div>

    <section class="surface-card overflow-hidden">
        <div class="p-lg border-b border-outline-variant/40 flex justify-between items-center gap-md">
            <div>
                <h3 class="font-title-lg text-title-lg text-on-surface">Documentos recientes</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Facturas y notas crédito ordenadas por actividad reciente.</p>
            </div>
            <a href="{{ route('reports.index') }}" class="text-primary font-label-md text-label-md hover:underline shrink-0">Ver indicadores</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/40">
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium whitespace-nowrap">Tipo</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium whitespace-nowrap">Número</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium">Cliente</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium whitespace-nowrap">Estado</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium whitespace-nowrap">Total</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium whitespace-nowrap">Fecha</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-center whitespace-nowrap">Acción</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm">
                    @forelse ($recentDocuments as $document)
                        <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                            <td class="px-md py-md text-on-surface whitespace-nowrap">{{ $document['type'] }}</td>
                            <td class="px-md py-md text-on-surface-variant whitespace-nowrap">{{ $document['number'] }}</td>
                            <td class="px-md py-md text-on-surface-variant whitespace-normal break-words">{{ $document['customer'] }}</td>
                            <td class="px-md py-md text-on-surface-variant whitespace-nowrap"><x-ui.badge :status="$document['status']" /></td>
                            <td class="px-md py-md text-on-surface font-mono whitespace-nowrap">{{ $document['total'] }}</td>
                            <td class="px-md py-md text-on-surface-variant whitespace-nowrap">{{ $document['date'] }}</td>
                            <td class="px-md py-md text-center whitespace-nowrap">
                                <a href="{{ $document['href'] }}" class="icon-btn inline-flex text-on-surface-variant hover:text-primary hover:bg-surface-container-low p-xs rounded-lg" aria-label="Ver documento" title="Ver documento">
                                    <span class="material-symbols-outlined text-xl">{{ $document['icon'] }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-lg py-xl text-center text-on-surface-variant font-body-sm text-body-sm">
                                Aún no hay facturas ni notas crédito reales para mostrar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
