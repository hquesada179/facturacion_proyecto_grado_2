<x-layouts.app
    title="{{ $customer->name }}"
    active="customers"
    screen="customer.show"
    resource-type="customer"
    :resource-id="$customer->id"
>
    <x-page-header eyebrow="Clientes" :title="$customer->name" description="Detalle del cliente y documentos asociados.">
        <x-slot:actions>
            @can('update', $customer)
                <a href="{{ route('customers.edit', $customer) }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Editar
                </a>
                <form method="POST" action="{{ route('customers.toggle-status', $customer) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">
                        {{ $customer->status === 'active' ? 'Inactivar' : 'Activar' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('¿Eliminar este cliente de forma permanente?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-md py-sm rounded-lg border border-outline-variant text-[#9b1c1c] hover:bg-[#fde8e8] font-label-md text-label-md">
                        Eliminar
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Identificación</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $customer->identification_type }} {{ $customer->identification_number }}@if ($customer->dv)-{{ $customer->dv }}@endif</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Correo</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $customer->email ?? '-' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Ciudad</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $customer->city ?? '-' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Estado</p>
            <x-ui.badge :status="$customer->status === 'active' ? 'Activo' : 'Inactivo'" />
        </div>
    </div>

    @if ($customer->is_final_consumer)
        <div class="mb-lg bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm">
            Este es el cliente "Consumidor Final" del prototipo, con identificación ficticia.
        </div>
    @endif

    <x-ui.data-table :table="[
        'title' => 'Facturas del cliente',
        'headers' => ['N° Factura', 'Fecha', 'Total', 'Estado'],
        'hideViewAll' => true,
        'emptyMessage' => 'Este cliente todavía no tiene facturas.',
        'rows' => [],
    ]" />
</x-layouts.app>
