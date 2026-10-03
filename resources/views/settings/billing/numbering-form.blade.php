<x-layouts.app title="Resolución de numeración" active="settings">
    <x-page-header eyebrow="Configuración" title="{{ $resolution->exists ? 'Editar resolución' : 'Nueva resolución de numeración' }}" description="Numeración simulada para el prototipo. No representa una autorización real de la DIAN." />

    <x-settings-tabs />

    <form class="space-y-lg" method="POST" action="{{ $formAction }}">
        @csrf
        @if ($formMethod === 'PUT')
            @method('PUT')
        @endif

        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Datos simulados de la resolución</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de documento</span>
                    <select name="document_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                        @foreach (\App\Enums\DocumentType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('document_type', $resolution->document_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">N.° de autorización simulado</span>
                    <input name="authorization_number_simulated" value="{{ old('authorization_number_simulated', $resolution->authorization_number_simulated) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Prefijo (máx. 4 caracteres)</span>
                    <input name="prefix" maxlength="4" value="{{ old('prefix', $resolution->prefix) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Clave técnica simulada</span>
                    <input name="simulated_technical_key" value="{{ old('simulated_technical_key', $resolution->simulated_technical_key) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Rango inicial</span>
                    <input name="range_from" value="{{ old('range_from', $resolution->range_from) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" min="1">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Rango final</span>
                    <input name="range_to" value="{{ old('range_to', $resolution->range_to) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" min="1">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Consecutivo actual</span>
                    <input name="current_consecutive" value="{{ old('current_consecutive', $resolution->current_consecutive ?? $resolution->range_from) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" min="1">
                </label>
                <label class="flex items-center gap-sm self-end">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resolution->exists ? $resolution->is_active : true)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                    <span class="font-label-md text-label-md text-on-surface">Activa</span>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Vigente desde</span>
                    <input name="valid_from" value="{{ old('valid_from', optional($resolution->valid_from)->format('Y-m-d')) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="date">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Vigente hasta</span>
                    <input name="valid_until" value="{{ old('valid_until', optional($resolution->valid_until)->format('Y-m-d')) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="date">
                </label>
            </div>
        </section>

        <div class="flex flex-col sm:flex-row justify-end gap-sm">
            <a href="{{ route('settings.billing') }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md text-center">Cancelar</a>
            <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center justify-center gap-sm" type="submit">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Guardar
            </button>
        </div>
    </form>
</x-layouts.app>
