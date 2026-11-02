<x-layouts.app
    title="{{ $product->name }}"
    active="products"
    screen="product.show"
    resource-type="product"
    :resource-id="$product->id"
>
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
                    <button type="submit" class="px-md py-sm rounded-lg border border-outline-variant text-error-soft-foreground hover:bg-error-soft font-label-md text-label-md">
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
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Precio {{ $product->tax_included ? '(incluye impuesto)' : 'base' }}</p>
            <p class="font-title-lg text-title-lg text-on-surface">${{ number_format((float) $product->price, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Categoría</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $product->category ?? 'Sin categoría' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Unidad</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ \App\Models\ProductService::UNITS[$product->unit] ?? $product->unit }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Control de inventario</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $product->is_inventoried ? 'Activado' : 'No aplica' }}</p>
        </div>
        @if ($product->is_inventoried)
            <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Existencia disponible</p>
                <p class="font-title-lg text-title-lg text-on-surface">{{ number_format((float) $product->stock_quantity, 2, ',', '.') }}</p>
            </div>
        @endif
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
        'rows' => $product->invoiceItems->map(fn ($item) => [
            'cells' => [
                $item->invoice?->number ?? 'Borrador #'.$item->invoice_id,
                (string) $item->quantity,
                \App\Support\ReportFormatter::money($item->line_total),
            ],
            'actions' => $item->invoice ? [
                ['label' => 'Ver factura', 'icon' => 'visibility', 'href' => route('invoices.show', $item->invoice), 'method' => 'GET'],
            ] : [],
        ])->all(),
    ]" />
</x-layouts.app>
