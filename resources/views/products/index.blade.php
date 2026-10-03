<x-layouts.app title="Productos y servicios" active="products">
    <x-page-header eyebrow="Productos y servicios" title="Productos y servicios" description="Catálogo real para la facturación simulada.">
        <x-slot:actions>
            @can('create', \App\Models\ProductService::class)
                <a href="{{ route('products.create') }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">add_box</span>
                    Nuevo producto
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg flex flex-col md:flex-row gap-md md:items-center md:justify-between">
        <form method="GET" action="{{ route('products.index') }}" class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10" placeholder="Buscar por código, nombre o descripción" type="search">
        </form>
        <form method="GET" action="{{ route('products.index') }}" class="flex gap-sm">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <select name="type" onchange="this.form.submit()" class="rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                <option value="">Todos los tipos</option>
                <option value="product" @selected(($filters['type'] ?? '') === 'product')>Producto</option>
                <option value="service" @selected(($filters['type'] ?? '') === 'service')>Servicio</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                <option value="">Todos los estados</option>
                @foreach (\App\Enums\ProductStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <x-ui.data-table :table="[
        'title' => 'Catálogo',
        'headers' => ['Código', 'Nombre', 'Tipo', 'Impuestos', 'Precio', 'Estado'],
        'hideViewAll' => true,
        'emptyMessage' => 'No hay productos ni servicios registrados.',
        'paginator' => $products,
        'rows' => $products->map(fn ($product) => [
            'cells' => [
                $product->sku,
                $product->name,
                $product->type === 'product' ? 'Producto' : 'Servicio',
                $product->taxes->pluck('code')->implode(', ') ?: 'Sin impuesto',
                '$'.number_format((float) $product->price, 0, ',', '.'),
                $product->status->label(),
            ],
            'actions' => array_filter([
                ['label' => 'Ver', 'icon' => 'visibility', 'href' => route('products.show', $product), 'method' => 'GET'],
                auth()->user()->can('update', $product) ? ['label' => 'Editar', 'icon' => 'edit', 'href' => route('products.edit', $product), 'method' => 'GET'] : null,
            ]),
        ])->all(),
    ]" />
</x-layouts.app>
