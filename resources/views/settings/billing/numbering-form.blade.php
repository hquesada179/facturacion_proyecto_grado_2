@php
    // Captured once, on the initial GET, and carried through a
    // validation-error round trip via the hidden `return_to` field below
    // (old('return_to', ...)) — reuses Laravel's own previous-URL
    // mechanism (url()->previous()), no new route/param introduced. Only
    // offered on create: that's the only path the invoice validation
    // screen's "Configurar numeración" CTA ever links to.
    $returnTo = old('return_to', $resolution->exists ? null : url()->previous());
    $showReturnLink = ! $resolution->exists && $returnTo && str_contains($returnTo, '/validacion');
@endphp

<x-layouts.app title="Resolución de numeración" active="settings">
    <x-page-header eyebrow="Configuración" title="{{ $resolution->exists ? 'Editar resolución' : 'Nueva resolución de numeración' }}" description="Numeración simulada para el prototipo. No representa una autorización real de la DIAN." />

    <x-settings-tabs />

    @unless ($resolution->exists)
        <x-ui.alert tone="neutral" icon="science" title="Ejemplo de configuración de prueba" class="mb-lg">
            Así se vería una resolución simulada típica — estos valores son solo una referencia, <strong>no los datos reales de tu empresa</strong>.
            <x-slot:footer>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-sm font-body-sm text-body-sm">
                    <div><dt class="text-on-surface-variant">Autorización</dt><dd class="font-mono">187640000001</dd></div>
                    <div><dt class="text-on-surface-variant">Prefijo</dt><dd class="font-mono">FEV</dd></div>
                    <div><dt class="text-on-surface-variant">Rango</dt><dd class="font-mono">1 a 1000</dd></div>
                    <div><dt class="text-on-surface-variant">Consecutivo actual</dt><dd class="font-mono">1</dd></div>
                    <div class="col-span-2 sm:col-span-1"><dt class="text-on-surface-variant">Vigencia</dt><dd class="font-mono">02/11/2026 a 02/11/2027</dd></div>
                </dl>
            </x-slot:footer>
        </x-ui.alert>
    @endunless

    <form class="space-y-lg" method="POST" action="{{ $formAction }}">
        @csrf
        @if ($formMethod === 'PUT')
            @method('PUT')
        @endif
        @if ($showReturnLink)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif

        <section class="surface-card p-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Datos simulados de la resolución</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de documento</span>
                    <select name="document_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                        @foreach (\App\Enums\DocumentType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('document_type', $resolution->document_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Selecciona el tipo de documento que utilizará esta numeración.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">N.° de autorización simulado</span>
                    <input name="authorization_number_simulated" value="{{ old('authorization_number_simulated', $resolution->authorization_number_simulated) }}" placeholder="187640000001" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Número ficticio que representa la autorización de numeración. No corresponde a una resolución real de la DIAN.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Prefijo (máx. 4 caracteres)</span>
                    <input name="prefix" maxlength="4" value="{{ old('prefix', $resolution->prefix) }}" placeholder="FEV" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Texto que aparecerá antes del consecutivo de la factura. Máximo 4 caracteres. Ejemplo: FEV-1.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Clave técnica simulada</span>
                    <input name="simulated_technical_key" value="{{ old('simulated_technical_key', $resolution->simulated_technical_key) }}" placeholder="CLAVE-DEMO-2026" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Valor ficticio usado únicamente por Fiscora para simular el proceso de emisión.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Rango inicial</span>
                    <input name="range_from" value="{{ old('range_from', $resolution->range_from) }}" placeholder="1" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" min="1">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Primer número permitido para esta resolución.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Rango final</span>
                    <input name="range_to" value="{{ old('range_to', $resolution->range_to) }}" placeholder="1000" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" min="1">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Último número permitido para esta resolución.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Consecutivo actual</span>
                    <input name="current_consecutive" value="{{ old('current_consecutive', $resolution->current_consecutive ?? $resolution->range_from) }}" placeholder="1" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="number" min="1">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Número que Fiscora utilizará como próximo consecutivo. Debe estar dentro del rango configurado.</p>
                </label>
                <label class="flex flex-col">
                    <span class="flex items-center gap-sm">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resolution->exists ? $resolution->is_active : true)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                        <span class="font-label-md text-label-md text-on-surface">Activa</span>
                    </span>
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Solo las resoluciones activas y vigentes pueden utilizarse para emitir facturas simuladas.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Vigente desde</span>
                    <input name="valid_from" value="{{ old('valid_from', optional($resolution->valid_from)->format('Y-m-d')) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="date">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Fecha a partir de la cual esta resolución puede utilizarse.</p>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Vigente hasta</span>
                    <input name="valid_until" value="{{ old('valid_until', optional($resolution->valid_until)->format('Y-m-d')) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="date">
                    <p class="mt-xs font-label-sm text-label-sm text-on-surface-variant">Fecha máxima en la que esta resolución seguirá siendo válida.</p>
                </label>
            </div>
        </section>

        <p class="font-body-sm text-body-sm text-on-surface-variant">
            Una vez guardada una resolución activa y vigente, podrás volver a tu factura y continuar con la emisión simulada.
            @if ($showReturnLink)
                <a href="{{ $returnTo }}" class="font-semibold text-primary hover:underline">Volver a mi factura ahora</a>
            @endif
        </p>

        <div class="flex flex-col sm:flex-row justify-end gap-sm">
            <a href="{{ route('settings.billing') }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md text-center">Cancelar</a>
            <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center justify-center gap-sm" type="submit">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Guardar
            </button>
        </div>
    </form>
</x-layouts.app>
