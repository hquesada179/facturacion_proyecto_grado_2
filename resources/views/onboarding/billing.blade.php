<x-layouts.auth title="Onboarding — Facturación">
    <x-auth-premium-layout>
        <x-slot:brandVisual>
            <x-onboarding.step-list current="billing" />
        </x-slot:brandVisual>

        <div class="mb-xl" data-reveal style="transition-delay:80ms">
            <h2 class="auth-premium-form-title">Configuración de facturación</h2>
            <p class="auth-premium-form-subtitle">Define cómo se calculan tus documentos simulados. Puedes ajustarlo después en Configuración.</p>
        </div>

        @if ($errors->any())
            <div class="auth-premium-error" data-reveal style="transition-delay:100ms">
                <span class="material-symbols-outlined mt-xs">error</span>
                <p class="font-body-sm text-body-sm">{{ $errors->first() }}</p>
            </div>
        @endif

        <form id="onboarding-billing-form" method="POST" action="{{ route('onboarding.billing.update') }}" data-reveal style="transition-delay:120ms">
            @csrf
            @method('PUT')

            <div class="auth-premium-fieldgrid mb-lg">
                <label class="auth-premium-field">
                    <span class="auth-premium-label">Moneda<span class="auth-premium-req">Requerido</span></span>
                    <select class="auth-premium-input" style="padding-left:1rem;" name="currency" required>
                        @foreach (\App\Models\Company::CURRENCIES as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', $company->currency) === $currency)>{{ $currency === 'COP' ? 'COP — Peso colombiano' : 'USD — Dólar' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="auth-premium-field">
                    <span class="auth-premium-label">Régimen tributario<span class="auth-premium-opt">Opcional</span></span>
                    <input class="auth-premium-input" style="padding-left:1rem;" name="tax_regime" value="{{ old('tax_regime', $company->tax_regime) }}" placeholder="Régimen común">
                </label>
                <label class="auth-premium-field span2">
                    <span class="auth-premium-label">Tributo principal<span class="auth-premium-opt">Opcional</span></span>
                    <select class="auth-premium-input" style="padding-left:1rem;" name="main_tax_id">
                        <option value="">Sin definir</option>
                        @foreach (\App\Models\Tax::query()->currentlyValid()->availableFor($company->id)->get() as $tax)
                            <option value="{{ $tax->id }}" @selected((string) old('main_tax_id', $company->main_tax_id) === (string) $tax->id)>{{ $tax->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="auth-premium-notice" data-reveal style="transition-delay:160ms">
                <span class="material-symbols-outlined" style="margin-top:1px;">warning</span>
                Las validaciones y emisiones realizadas en Fiscora son simuladas y no sustituyen procesos tributarios reales.
            </div>
        </form>

        {{--
            Optional, additive section: lets the user set up the
            resolution that unblocks invoice issuance right here instead
            of discovering it's missing later when trying to emit their
            first invoice. Independent <form> (own route/action), same
            pattern the "Cliente y producto" step already uses for its
            two independent sub-forms — never gates the "Continuar"
            button below, which still only submits the currency form.
        --}}
        <div class="auth-premium-fieldgroup" data-reveal style="transition-delay:200ms">
            <p class="auth-premium-fieldgroup-title">
                <span class="material-symbols-outlined">tag</span>
                Numeración de facturas
            </p>

            @if ($numberingResolution)
                <div class="auth-premium-mini-card mb-sm">
                    <div class="auth-premium-mini-card-row"><span class="k">Prefijo</span><span class="v">{{ $numberingResolution->prefix }}</span></div>
                    <div class="auth-premium-mini-card-row"><span class="k">Rango</span><span class="v">{{ $numberingResolution->range_from }} – {{ $numberingResolution->range_to }}</span></div>
                    <div class="auth-premium-mini-card-row"><span class="k">Vigente</span><span class="v">{{ $numberingResolution->valid_from->format('Y-m-d') }} a {{ $numberingResolution->valid_until->format('Y-m-d') }}</span></div>
                </div>
                <p class="font-label-sm text-label-sm" style="color:var(--color-success-soft-foreground);">✓ Ya tienes una resolución de numeración vigente para facturas.</p>
            @else
                <div class="auth-premium-notice mb-md">
                    <span class="material-symbols-outlined" style="margin-top:1px;">warning</span>
                    Necesitas una resolución de numeración vigente antes de poder emitir tu primera factura. Puedes configurar una simulada ahora o hacerlo más tarde desde Configuración — no podrás emitir sin ella.
                </div>

                @can('manage-numbering')
                    <form method="POST" action="{{ route('onboarding.numbering.store') }}" class="auth-premium-fieldgrid">
                        @csrf
                        <input type="hidden" name="is_active" value="1">
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Prefijo<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" name="prefix" maxlength="4" value="{{ old('prefix', 'FV') }}" placeholder="FV" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Número inicial<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" type="number" min="1" name="range_from" value="{{ old('range_from', 1) }}" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Número final<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" type="number" min="1" name="range_to" value="{{ old('range_to', 1000) }}" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Vigente desde<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" type="date" name="valid_from" value="{{ old('valid_from', now()->toDateString()) }}" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Vigente hasta<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" type="date" name="valid_until" value="{{ old('valid_until', now()->addYear()->toDateString()) }}" required>
                        </label>
                        <div class="span2">
                            <button type="submit" class="auth-premium-submit" style="width:auto;padding:0.6rem 1.2rem;font-size:13px;">Configurar numeración de prueba</button>
                        </div>
                    </form>
                @else
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Pide a un administrador de tu empresa que configure la numeración de facturas en Configuración → Facturación e impuestos.</p>
                @endcan
            @endif
        </div>

        <div class="flex items-center justify-between mt-lg pt-lg" style="border-top:1px solid var(--color-outline-variant);">
            <a href="{{ route('onboarding.show', ['step' => 'company']) }}" class="auth-premium-back-link">Volver</a>
            <button type="submit" form="onboarding-billing-form" class="auth-premium-submit" style="width:auto;padding-left:1.5rem;padding-right:1.5rem;">
                Continuar
            </button>
        </div>
    </x-auth-premium-layout>
</x-layouts.auth>
