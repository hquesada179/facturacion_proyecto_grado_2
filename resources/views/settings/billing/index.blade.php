<x-layouts.app title="Facturación e impuestos" active="settings">
    <x-page-header eyebrow="Configuración" title="Facturación e impuestos" description="Numeración simulada y catálogo de impuestos del prototipo.">
        <x-slot:actions>
            <a href="{{ route('settings.billing.numbering.create') }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Nueva resolución
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-settings-tabs />

    @if ($expiringCount > 0 || $exhaustingCount > 0)
        <div class="mb-lg space-y-sm">
            @if ($expiringCount > 0)
                <div class="bg-[#fdf6b2] border border-[#723b13]/20 text-[#723b13] rounded-lg p-md font-body-sm text-body-sm flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">warning</span>
                    {{ $expiringCount }} {{ $expiringCount === 1 ? 'resolución' : 'resoluciones' }} por vencer en los próximos 30 días.
                </div>
            @endif
            @if ($exhaustingCount > 0)
                <div class="bg-[#fde8e8] border border-[#9b1c1c]/20 text-[#9b1c1c] rounded-lg p-md font-body-sm text-body-sm flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">report</span>
                    {{ $exhaustingCount }} {{ $exhaustingCount === 1 ? 'rango' : 'rangos' }} de numeración próximos a agotarse.
                </div>
            @endif
        </div>
    @endif

    <x-ui.data-table :table="[
        'title' => 'Resoluciones de numeración simulada',
        'headers' => ['Tipo de documento', 'Prefijo', 'Rango', 'Consecutivo actual', 'Vigencia', 'Estado'],
        'hideViewAll' => true,
        'emptyMessage' => 'Todavía no hay resoluciones de numeración configuradas.',
        'rows' => $resolutions->map(fn ($resolution) => [
            'cells' => [
                $resolution->document_type->label(),
                $resolution->prefix,
                $resolution->range_from.' - '.$resolution->range_to,
                $resolution->current_consecutive,
                $resolution->valid_from->format('Y-m-d').' a '.$resolution->valid_until->format('Y-m-d'),
                $resolution->status->label(),
            ],
            'actions' => [
                ['label' => 'Editar', 'icon' => 'edit', 'href' => route('settings.billing.numbering.edit', $resolution), 'method' => 'GET'],
            ],
        ])->all(),
    ]" />

    <div class="mt-lg">
        <x-ui.data-table :table="[
            'title' => 'Catálogo de impuestos',
            'headers' => ['Código', 'Nombre', 'Tarifa', 'Naturaleza', 'Estado'],
            'hideViewAll' => true,
            'emptyMessage' => 'No hay impuestos en el catálogo.',
            'rows' => $taxes->map(fn ($tax) => [
                'cells' => [
                    $tax->code,
                    $tax->name,
                    number_format((float) $tax->rate, 2).'%',
                    $tax->nature ?? '-',
                    $tax->is_active ? 'Activo' : 'Inactivo',
                ],
                'actions' => [
                    [
                        'label' => $tax->is_active ? 'Desactivar' : 'Activar',
                        'icon' => $tax->is_active ? 'toggle_off' : 'toggle_on',
                        'href' => route('settings.billing.taxes.toggle', $tax),
                        'method' => 'PATCH',
                    ],
                ],
            ])->all(),
        ]" />
    </div>
</x-layouts.app>
