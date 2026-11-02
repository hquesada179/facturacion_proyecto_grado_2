<x-layouts.app title="Nueva factura" active="invoices">
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Agrega productos y servicios reales. La tabla siempre refleja lo que ingreses." />

    <x-invoice-stepper :current="2" :invoice="$invoice" />

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Agregar producto o servicio</h3>
        <form method="POST" action="{{ route('invoices.draft.items.store', $invoice) }}" class="space-y-md">
            @csrf
            <label class="block">
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Buscar producto por código, nombre o descripción</span>
                <input type="search" id="product-quick-search" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" placeholder="Escribe para filtrar la lista...">
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Producto</span>
                    <select id="product-select" name="product_service_id" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        <option value="">— Línea personalizada —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" data-search="{{ mb_strtolower($product->sku.' '.$product->name.' '.$product->description) }}" @selected($selectedProductId === $product->id)>{{ $product->sku }} — {{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Descripción</span>
                    <input id="description-input" name="description" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Unidad</span>
                    <input id="unit-input" name="unit" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text" value="unidad">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Cantidad</span>
                    <input name="quantity" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="number" step="0.01" min="0" value="1">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Valor unitario</span>
                    <input id="price-input" name="unit_price" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="number" step="0.01" min="0" value="0">
                    <span id="tax-included-hint" class="hidden font-label-sm text-label-sm text-on-surface-variant mt-xs">Este precio ya incluye el impuesto; la base gravable se calculará automáticamente.</span>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Descuento %</span>
                    <input name="discount_percent" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="number" step="0.01" min="0" max="100" value="0">
                </label>
            </div>

            <div>
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Impuestos</span>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-xs">
                    @foreach ($taxes as $tax)
                        <label class="flex items-center gap-sm">
                            <input type="checkbox" class="tax-checkbox rounded border-outline-variant text-primary focus:ring-primary/20" name="tax_ids[]" value="{{ $tax->id }}">
                            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $tax->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                    <span class="material-symbols-outlined text-[18px]">add_box</span>
                    Agregar línea
                </button>
            </div>
        </form>
    </section>

    <details class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <summary class="font-title-lg text-title-lg text-on-surface cursor-pointer select-none">¿No encuentras el producto? Créalo sin salir de la factura</summary>
        <form method="POST" action="{{ route('invoices.draft.items.quick-create-product', $invoice) }}" class="space-y-md mt-md">
            @csrf
            <input type="hidden" name="status" value="active">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Código interno</span>
                    <input name="sku" value="{{ old('sku') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Nombre</span>
                    <input name="name" value="{{ old('name') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo</span>
                    <select id="quick-product-type-select" name="type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        <option value="product" @selected(old('type') === 'product')>Producto</option>
                        <option value="service" @selected(old('type', 'service') === 'service')>Servicio</option>
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Categoría</span>
                    <input name="category" value="{{ old('category') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text" placeholder="Opcional">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Unidad de medida</span>
                    @php($quickUnit = old('unit', 'unidad'))
                    <select name="unit" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        @foreach (\App\Models\ProductService::UNITS as $value => $label)
                            <option value="{{ $value }}" @selected($quickUnit === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Precio base</span>
                    <input name="price" value="{{ old('price') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="number" step="0.01" min="0">
                </label>
                <label class="flex items-center gap-sm mt-lg">
                    <input type="checkbox" name="tax_included" value="1" @checked(old('tax_included')) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                    <span class="font-label-md text-label-md text-on-surface">Precio incluye impuesto</span>
                </label>
                <label id="quick-inventory-checkbox-label" class="flex items-center gap-sm mt-lg">
                    <input type="checkbox" id="quick-inventory-checkbox" name="is_inventoried" value="1" @checked(old('is_inventoried')) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                    <span class="font-label-md text-label-md text-on-surface">Controlar inventario</span>
                </label>
                <label id="quick-stock-quantity-field">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Existencia disponible</span>
                    <input name="stock_quantity" value="{{ old('stock_quantity') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="number" step="0.01" min="0">
                </label>
                <label class="md:col-span-2">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Descripción</span>
                    <input name="description" value="{{ old('description') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
            </div>
            <div>
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Impuestos</span>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-xs">
                    @foreach ($taxes as $tax)
                        <label class="flex items-center gap-sm">
                            <input type="checkbox" name="taxes[]" value="{{ $tax->id }}" class="rounded border-outline-variant text-primary focus:ring-primary/20">
                            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $tax->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end">
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    Crear y seleccionar este producto
                </button>
            </div>
        </form>
    </details>

    <div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/40 mb-lg">
        <div class="p-lg border-b border-surface-variant">
            <h3 class="font-title-lg text-title-lg text-on-surface">Productos y servicios agregados</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-lowest border-b border-surface-variant">
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium">Descripción</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium">Cantidad</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium">Valor unitario</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium">Descuento</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium">Impuestos</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium">Total línea</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm">
                    @forelse ($items as $item)
                        <tr class="border-b border-surface-variant last:border-b-0">
                            <td class="px-lg py-md text-on-surface-variant">{{ $item->description }}</td>
                            <td class="px-lg py-md">
                                <form method="POST" action="{{ route('invoices.draft.items.update', [$invoice, $item]) }}" class="flex items-center gap-xs">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="product_service_id" value="{{ $item->product_service_id }}">
                                    <input type="hidden" name="description" value="{{ $item->description }}">
                                    <input type="hidden" name="unit" value="{{ $item->unit }}">
                                    <input type="hidden" name="unit_price" value="{{ $item->unit_price }}">
                                    <input type="hidden" name="discount_percent" value="{{ $item->discount_percent }}">
                                    @foreach ($item->itemTaxes as $itemTax)
                                        <input type="hidden" name="tax_ids[]" value="{{ $itemTax->tax_id }}">
                                    @endforeach
                                    <input name="quantity" value="{{ $item->quantity }}" class="w-20 px-sm py-xs rounded-lg border border-outline-variant bg-surface font-mono text-body-sm" type="number" step="0.01" min="0">
                                    <button type="submit" class="text-primary hover:underline font-label-sm text-label-sm whitespace-nowrap" title="Actualizar cantidad">Actualizar</button>
                                </form>
                            </td>
                            <td class="px-lg py-md font-mono text-on-surface">{{ \App\Support\ReportFormatter::money($item->unit_price) }}</td>
                            <td class="px-lg py-md font-mono text-on-surface">{{ \App\Support\ReportFormatter::money($item->discount_total) }}</td>
                            <td class="px-lg py-md text-on-surface-variant">{{ $item->itemTaxes->pluck('code')->implode(', ') ?: 'Sin impuesto' }}</td>
                            <td class="px-lg py-md font-mono text-on-surface">{{ \App\Support\ReportFormatter::money($item->line_total) }}</td>
                            <td class="px-lg py-md text-center">
                                <form method="POST" action="{{ route('invoices.draft.items.destroy', [$invoice, $item]) }}" onsubmit="return confirm('¿Eliminar esta línea?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-on-surface-variant hover:text-error-soft-foreground p-xs rounded-lg" aria-label="Eliminar">
                                        <span class="material-symbols-outlined text-xl">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-lg py-xl text-center text-on-surface-variant font-body-sm text-body-sm">Todavía no hay productos agregados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-between">
        <a href="{{ route('invoices.draft.customer', $invoice) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">Volver a cliente</a>
        <a href="{{ route('invoices.draft.summary', $invoice) }}" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover">Continuar a resumen</a>
    </div>

    <script>
        (function () {
            const products = @json($productsJson);

            const search = document.getElementById('product-quick-search');
            const select = document.getElementById('product-select');
            const description = document.getElementById('description-input');
            const unit = document.getElementById('unit-input');
            const price = document.getElementById('price-input');
            const taxIncludedHint = document.getElementById('tax-included-hint');

            function applySelectedProduct() {
                const product = products.find(function (p) { return String(p.id) === select.value; });
                document.querySelectorAll('.tax-checkbox').forEach(function (checkbox) {
                    checkbox.checked = false;
                });

                if (! product) {
                    if (taxIncludedHint) {
                        taxIncludedHint.classList.add('hidden');
                    }

                    return;
                }

                description.value = product.name;
                unit.value = product.unit;
                price.value = product.price;
                product.tax_ids.forEach(function (taxId) {
                    const checkbox = document.querySelector('.tax-checkbox[value="' + taxId + '"]');
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });

                if (taxIncludedHint) {
                    taxIncludedHint.classList.toggle('hidden', ! product.tax_included);
                }
            }

            select.addEventListener('change', applySelectedProduct);

            if (search) {
                const options = Array.from(select.options);

                search.addEventListener('input', function () {
                    const term = search.value.trim().toLowerCase();
                    let firstMatch = null;

                    options.forEach(function (option) {
                        if (! option.value) {
                            return;
                        }
                        const matches = term === '' || (option.dataset.search || '').includes(term);
                        option.hidden = ! matches;
                        if (matches && firstMatch === null) {
                            firstMatch = option;
                        }
                    });

                    if (firstMatch) {
                        select.value = firstMatch.value;
                        applySelectedProduct();
                    }
                });
            }

            // A product just created via "quick create" comes back already
            // chosen in the option list server-side — apply its autofill
            // immediately so the user doesn't have to re-touch the select.
            if (select.value) {
                applySelectedProduct();
            }
        })();

        (function () {
            const typeSelect = document.getElementById('quick-product-type-select');
            const inventoryLabel = document.getElementById('quick-inventory-checkbox-label');
            const inventoryCheckbox = document.getElementById('quick-inventory-checkbox');
            const stockField = document.getElementById('quick-stock-quantity-field');

            if (! typeSelect || ! inventoryCheckbox) {
                return;
            }

            function isProduct() {
                return typeSelect.value === 'product';
            }

            function refresh() {
                inventoryLabel.style.display = isProduct() ? '' : 'none';

                if (! isProduct()) {
                    inventoryCheckbox.checked = false;
                }

                stockField.style.display = inventoryCheckbox.checked ? '' : 'none';
            }

            typeSelect.addEventListener('change', refresh);
            inventoryCheckbox.addEventListener('change', refresh);
            refresh();
        })();
    </script>
</x-layouts.app>
