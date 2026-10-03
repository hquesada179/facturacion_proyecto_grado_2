@csrf
@if ($formMethod === 'PUT')
    @method('PUT')
@endif

<section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Identificación</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de persona</span>
            <select name="person_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                @foreach (['natural' => 'Persona natural', 'juridica' => 'Persona jurídica'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('person_type', $customer->person_type ?? 'natural') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Nombre o razón social</span>
            <input name="name" value="{{ old('name', $customer->name) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de identificación</span>
            <select name="identification_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                @foreach (\App\Models\Customer::IDENTIFICATION_TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(old('identification_type', $customer->identification_type ?? 'CC') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Número de identificación</span>
            <div class="flex gap-sm">
                <input name="identification_number" value="{{ old('identification_number', $customer->identification_number) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                @if ($customer->exists && $customer->dv)
                    <span class="px-md py-sm rounded-lg bg-surface-container text-on-surface-variant font-mono" title="Dígito de verificación">{{ $customer->dv }}</span>
                @endif
            </div>
        </label>
    </div>
</section>

<section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Contacto y ubicación</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
            <input name="email" value="{{ old('email', $customer->email) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="email">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Teléfono <span class="text-on-surface-variant font-body-sm">(obligatorio si es persona jurídica)</span></span>
            <input name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label class="md:col-span-2">
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Dirección <span class="text-on-surface-variant font-body-sm">(obligatoria si es persona jurídica)</span></span>
            <input name="address" value="{{ old('address', $customer->address) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Ciudad</span>
            <input name="city" value="{{ old('city', $customer->city) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Departamento</span>
            <input name="department" value="{{ old('department', $customer->department) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">País</span>
            <input name="country" value="{{ old('country', $customer->country ?? 'Colombia') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
    </div>
</section>

<section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Datos fiscales</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Responsabilidad tributaria</span>
            <input name="tax_responsibility" value="{{ old('tax_responsibility', $customer->tax_responsibility) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Tributo</span>
            <select name="tax_id" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                <option value="">Sin definir</option>
                @foreach ($taxes as $tax)
                    <option value="{{ $tax->id }}" @selected(old('tax_id', $customer->tax_id) == $tax->id)>{{ $tax->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="block font-label-md text-label-md text-on-surface mb-xs">Estado</span>
            <select name="status" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                @foreach (\App\Models\Customer::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $customer->status ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
</section>

<div class="flex flex-col sm:flex-row justify-end gap-sm">
    <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md text-center">Cancelar</a>
    <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center justify-center gap-sm" type="submit">
        <span class="material-symbols-outlined text-[18px]">save</span>
        Guardar
    </button>
</div>
