@csrf
@if ($formMethod === 'PUT')
    @method('PUT')
@endif

<section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Datos comerciales</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Código interno</span>
            <input name="sku" value="{{ old('sku', $product->sku) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Nombre</span>
            <input name="name" value="{{ old('name', $product->name) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo</span>
            <select name="type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                <option value="product" @selected(old('type', $product->type) === 'product')>Producto</option>
                <option value="service" @selected(old('type', $product->type) === 'service')>Servicio</option>
            </select>
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Unidad de medida</span>
            <input name="unit" value="{{ old('unit', $product->unit) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Precio base</span>
            <input name="price" value="{{ old('price', $product->price) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" step="0.01" min="0">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Estado</span>
            <select name="status" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                @foreach (\App\Enums\ProductStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $product->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-sm">
            <input type="checkbox" name="tax_included" value="1" @checked(old('tax_included', $product->tax_included)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
            <span class="font-label-md text-label-md text-on-surface">Precio incluye impuesto</span>
        </label>
        <label class="md:col-span-2">
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Descripción</span>
            <textarea name="description" class="w-full min-h-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">{{ old('description', $product->description) }}</textarea>
        </label>
    </div>
</section>

<section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Impuestos aplicables</h3>
    @php($selectedTaxes = old('taxes', $product->exists ? $product->taxes->pluck('id')->all() : []))
    <div class="grid grid-cols-1 md:grid-cols-2 gap-xs">
        @foreach ($taxes as $tax)
            <label class="flex items-center gap-sm">
                <input type="checkbox" name="taxes[]" value="{{ $tax->id }}" @checked(in_array($tax->id, $selectedTaxes)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $tax->name }}</span>
            </label>
        @endforeach
    </div>
</section>

<div class="flex flex-col sm:flex-row justify-end gap-sm">
    <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md text-center">Cancelar</a>
    <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center justify-center gap-sm" type="submit">
        <span class="material-symbols-outlined text-[18px]">save</span>
        Guardar
    </button>
</div>
