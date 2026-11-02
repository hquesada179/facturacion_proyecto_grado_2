@php
    $money = fn ($value) => \App\Support\ReportFormatter::money($value);
    $integer = fn ($value) => \App\Support\ReportFormatter::integer($value);
    $currentQuery = request()->query();
@endphp

<x-layouts.app title="Reportes e indicadores" active="reports" wide>
    <x-page-header
        eyebrow="Reportes"
        title="{{ $activeSection === 'errors' ? 'Errores y productividad' : 'Indicadores documentales del prototipo' }}"
        description="Métricas calculadas desde facturas, notas crédito y eventos registrados para {{ $range->label }}."
    >
        <x-slot:actions>
            <a href="{{ route('reports.export', $currentQuery) }}" class="rounded-lg border border-outline-variant px-md py-sm flex items-center gap-sm font-label-md text-label-md text-on-surface hover:border-primary hover:text-primary">
                <span class="material-symbols-outlined text-[18px]">download</span>
                CSV
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-lg flex flex-wrap gap-sm">
        <a href="{{ route('reports.index', $currentQuery) }}" @class([
            'px-md py-sm rounded-lg font-label-md text-label-md',
            'bg-primary text-on-primary' => $activeSection === 'general',
            'bg-surface-container-lowest border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary' => $activeSection !== 'general',
        ])>Resumen documental</a>
        <a href="{{ route('reports.errors', $currentQuery) }}" @class([
            'px-md py-sm rounded-lg font-label-md text-label-md',
            'bg-primary text-on-primary' => $activeSection === 'errors',
            'bg-surface-container-lowest border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary' => $activeSection !== 'errors',
        ])>Errores y productividad</a>
    </div>

    @if ($isLimitedToOwnDocuments)
        <div class="mb-lg rounded-lg border border-outline-variant bg-surface-container-lowest p-md text-on-surface-variant font-body-sm text-body-sm">
            Tu rol facturador limita estos indicadores a documentos creados por tu usuario.
        </div>
    @endif

    <form method="GET" action="{{ route($activeSection === 'errors' ? 'reports.errors' : 'reports.index') }}" class="surface-card p-lg mb-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-md">
            <label>
                <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Rango</span>
                <select name="date_range" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                    @foreach ($filterOptions['ranges'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['date_range'] ?? 'last_30_days') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Desde</span>
                <input name="start_date" value="{{ $filters['start_date'] ?? $range->start->toDateString() }}" type="date" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
            </label>
            <label>
                <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Hasta</span>
                <input name="end_date" value="{{ $filters['end_date'] ?? $range->end->toDateString() }}" type="date" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
            </label>
            <label>
                <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Tipo</span>
                <select name="document_type" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                    @foreach ($filterOptions['documentTypes'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['document_type'] ?? 'all') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Estado</span>
                <select name="status" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                    <option value="">Todos</option>
                    @foreach ($filterOptions['statuses'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Cliente</span>
                <select name="customer_id" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                    <option value="">Todos</option>
                    @foreach ($filterOptions['customers'] as $customer)
                        <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-md flex flex-col md:flex-row md:items-end gap-md">
            @if ($canViewUserBreakdown)
                <label class="md:w-72">
                    <span class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Usuario</span>
                    <select name="user_id" class="w-full rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                        <option value="">Todos</option>
                        @foreach ($filterOptions['users'] as $reportUser)
                            <option value="{{ $reportUser->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $reportUser->id)>{{ $reportUser->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <div class="flex gap-sm">
                <button type="submit" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                    Aplicar
                </button>
                <a href="{{ route($activeSection === 'errors' ? 'reports.errors' : 'reports.index') }}" class="rounded-lg border border-outline-variant px-md py-sm flex items-center gap-sm font-label-md text-label-md text-on-surface-variant hover:border-primary hover:text-primary">
                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                    Limpiar
                </a>
            </div>
        </div>
    </form>

    <section class="mb-xl">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-md">
            @foreach ($documentReport['summaryCards'] as $metric)
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
            @endforeach
        </div>
    </section>

    <section class="surface-card p-lg mb-lg">
        <div class="mb-md">
            <h3 class="font-title-lg text-title-lg text-on-surface">Documentos por día</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Conteo de documentos creados dentro del rango filtrado.</p>
        </div>
        @if ($documentReport['series']->isNotEmpty())
            <div class="h-64 flex items-end gap-sm border-l border-b border-outline-variant/50 px-md py-md overflow-x-auto">
                @foreach ($documentReport['series'] as $bar)
                    <div class="min-w-10 flex-1 flex flex-col items-center justify-end gap-xs">
                        <span class="font-label-sm text-label-sm text-on-surface-variant tabular-nums">{{ $bar['count'] }}</span>
                        <div class="w-full rounded-t-lg bg-primary min-h-2" style="height: {{ $bar['height'] }}%"></div>
                        <span class="font-label-sm text-label-sm text-on-surface-variant whitespace-nowrap">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-lg border border-outline-variant/40 bg-surface p-lg text-center text-on-surface-variant font-body-sm text-body-sm">
                No hay documentos en el rango seleccionado.
            </div>
        @endif
    </section>

    <section class="mb-xl">
        <div class="mb-md">
            <h3 class="font-title-lg text-title-lg text-on-surface">Productividad</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Tiempos de emisión, validación, reprocesos y correcciones documentales.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-md">
            @foreach ($productivityReport['cards'] as $metric)
                <div>
                    <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
                    @if (! empty($metric['hint']))
                        <p class="mt-xs px-xs font-label-sm text-label-sm text-on-surface-variant">{{ $metric['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-xl">
        <div class="mb-md">
            <h3 class="font-title-lg text-title-lg text-on-surface">Errores y validaciones</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Eventos reales registrados en la trazabilidad del prototipo.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-md mb-lg">
            @foreach ($errorReport['cards'] as $metric)
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
            @endforeach
        </div>

        <div class="surface-card overflow-hidden">
            <div class="p-lg border-b border-outline-variant/40">
                <h4 class="font-title-lg text-title-lg text-on-surface">Reglas o campos con más fallos</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant/40">
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Código</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Regla</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Campo</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Severidad</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Eventos</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-sm text-body-sm">
                        @forelse ($errorReport['topFailures'] as $failure)
                            <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                                <td class="px-md py-md text-on-surface align-middle whitespace-nowrap">{{ $failure['code'] }}</td>
                                <td class="px-md py-md text-on-surface-variant align-middle">{{ $failure['rule'] }}</td>
                                <td class="px-md py-md text-on-surface-variant align-middle whitespace-nowrap">{{ $failure['field'] }}</td>
                                <td class="px-md py-md text-on-surface-variant align-middle whitespace-nowrap">{{ $failure['severity'] }}</td>
                                <td class="px-md py-md text-on-surface text-right align-middle tabular-nums">{{ $integer($failure['count']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-lg py-xl text-center text-on-surface-variant font-body-sm text-body-sm">
                                    No hay resultados de validación persistidos con regla/campo en el rango seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-lg mb-lg">
        <section class="surface-card overflow-hidden">
            <div class="p-lg border-b border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface">Reporte por estado</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant/40">
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Tipo</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-center align-middle">Estado</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Documentos</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-sm text-body-sm">
                        @forelse ($documentReport['byStatus'] as $row)
                            <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                                <td class="px-md py-md text-on-surface align-middle">{{ $row['document_type'] }}</td>
                                <td class="px-md py-md text-center align-middle"><x-ui.badge :status="$row['status']" /></td>
                                <td class="px-md py-md text-on-surface text-right align-middle tabular-nums">{{ $integer($row['documents']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-lg py-xl text-center text-on-surface-variant">No hay estados para el filtro aplicado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="p-lg border-b border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface">Reporte por clientes</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant/40">
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Cliente</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Facturas</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">NC</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Neto</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-sm text-body-sm">
                        @forelse ($documentReport['byClients'] as $row)
                            <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                                <td class="px-md py-md text-on-surface align-middle whitespace-normal break-words">{{ $row['customer'] }}</td>
                                <td class="px-md py-md text-on-surface-variant text-right align-middle tabular-nums">{{ $integer($row['invoices']) }}</td>
                                <td class="px-md py-md text-on-surface-variant text-right align-middle tabular-nums">{{ $integer($row['credit_notes']) }}</td>
                                <td class="px-md py-md text-on-surface text-right align-middle font-mono tabular-nums">{{ $money($row['net_total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-lg py-xl text-center text-on-surface-variant">No hay clientes para el filtro aplicado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-lg mb-lg">
        <section class="surface-card overflow-hidden">
            <div class="p-lg border-b border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface">Productos y servicios</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant/40">
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Código</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Descripción</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Facturado</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Acreditado</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-sm text-body-sm">
                        @forelse ($documentReport['byProducts'] as $row)
                            <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                                <td class="px-md py-md text-on-surface-variant align-middle whitespace-nowrap">{{ $row['code'] }}</td>
                                <td class="px-md py-md text-on-surface align-middle whitespace-normal break-words">{{ $row['description'] }}</td>
                                <td class="px-md py-md text-on-surface text-right align-middle font-mono tabular-nums">{{ $money($row['invoiced_total']) }}</td>
                                <td class="px-md py-md text-on-surface-variant text-right align-middle font-mono tabular-nums">{{ $money($row['credited_total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-lg py-xl text-center text-on-surface-variant">No hay productos o servicios en el rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="p-lg border-b border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface">Reporte por usuarios</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant/40">
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Usuario</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Facturas</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Emitidas</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">NC</th>
                            <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle">Total</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-sm text-body-sm">
                        @if (! $canViewUserBreakdown)
                            <tr><td colspan="5" class="px-lg py-xl text-center text-on-surface-variant">Tu rol no muestra desglose por usuario.</td></tr>
                        @else
                            @forelse ($documentReport['byUsers'] as $row)
                                <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                                    <td class="px-md py-md text-on-surface align-middle">{{ $row['user'] }}</td>
                                    <td class="px-md py-md text-on-surface-variant text-right align-middle tabular-nums">{{ $integer($row['invoices']) }}</td>
                                    <td class="px-md py-md text-on-surface-variant text-right align-middle tabular-nums">{{ $integer($row['emitted']) }}</td>
                                    <td class="px-md py-md text-on-surface-variant text-right align-middle tabular-nums">{{ $integer($row['credit_notes']) }}</td>
                                    <td class="px-md py-md text-on-surface text-right align-middle font-mono tabular-nums">{{ $money($row['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-lg py-xl text-center text-on-surface-variant">No hay usuarios con documentos en el rango.</td></tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="surface-card overflow-hidden">
        <div class="p-lg border-b border-outline-variant/40 flex flex-col md:flex-row md:items-center md:justify-between gap-sm">
            <div>
                <h3 class="font-title-lg text-title-lg text-on-surface">Documentos filtrados</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Primeros 25 documentos que cumplen los filtros actuales.</p>
            </div>
            <a href="{{ route('reports.export', $currentQuery) }}" class="text-primary font-label-md text-label-md hover:underline shrink-0">Exportar CSV completo</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/40">
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle whitespace-nowrap">Tipo</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle whitespace-nowrap">Número</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle">Cliente</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle whitespace-nowrap">Usuario</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-center align-middle whitespace-nowrap">Estado</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-right align-middle whitespace-nowrap">Total</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle whitespace-nowrap">Fecha</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-center align-middle whitespace-nowrap">Acción</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm">
                    @forelse ($documentReport['documents'] as $document)
                        <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                            <td class="px-md py-md text-on-surface align-middle whitespace-nowrap">{{ $document['type'] }}</td>
                            <td class="px-md py-md text-on-surface-variant align-middle whitespace-nowrap">{{ $document['number'] }}</td>
                            <td class="px-md py-md text-on-surface-variant align-middle whitespace-normal break-words">{{ $document['customer'] }}</td>
                            <td class="px-md py-md text-on-surface-variant align-middle whitespace-nowrap">{{ $document['user'] }}</td>
                            <td class="px-md py-md text-center align-middle whitespace-nowrap"><x-ui.badge :status="$document['status']" /></td>
                            <td class="px-md py-md text-on-surface text-right align-middle font-mono tabular-nums whitespace-nowrap">{{ $document['formatted_total'] }}</td>
                            <td class="px-md py-md text-on-surface-variant align-middle whitespace-nowrap">{{ $document['date'] }}</td>
                            <td class="px-md py-md text-center align-middle whitespace-nowrap">
                                <a href="{{ $document['href'] }}" class="icon-btn inline-flex text-on-surface-variant hover:text-primary hover:bg-surface-container-low p-xs rounded-lg" aria-label="Ver documento" title="Ver documento">
                                    <span class="material-symbols-outlined text-xl">visibility</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-lg py-xl text-center text-on-surface-variant">No hay documentos para el filtro aplicado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
