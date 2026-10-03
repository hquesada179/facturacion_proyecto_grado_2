<x-layouts.app title="{{ $product->name }}" active="products">
    <x-page-header eyebrow="Productos y servicios" :title="$product->name" description="Detalle del producto o servicio.">
        <x-slot:actions>
            @can('update', $product)
                <a href="{{ route('products.edit', $product) }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Editar
                </a>
                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('¿Eliminar este producto de forma permanente?');">
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
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Código</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $product->sku }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Tipo</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $product->type === 'product' ? 'Producto' : 'Servicio' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Impuestos</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $product->taxes->pluck('code')->implode(', ') ?: 'Sin impuesto' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Precio base</p>
            <p class="font-title-lg text-title-lg text-on-surface">${{ number_format((float) $product->price, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-lg">
        <x-ui.badge :status="$product->status->label()" />
    </div>

    @if ($product->description)
        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Descripción</h3>
            <p class="font-body-md text-body-md text-on-surface-variant">{{ $product->description }}</p>
        </section>
    @endif

    <x-ui.data-table :table="[
        'title' => 'Uso en facturas',
        'headers' => ['Factura', 'Cantidad', 'Total'],
        'hideViewAll' => true,
        'emptyMessage' => 'Este producto todavía no se ha facturado.',
        'rows' => [],
    ]" />
</x-layouts.app>
