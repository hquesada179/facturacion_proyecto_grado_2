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

        <form method="POST" action="{{ route('onboarding.billing.update') }}" data-reveal style="transition-delay:120ms">
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

            <div class="flex items-center justify-between mt-lg pt-lg" style="border-top:1px solid var(--color-outline-variant);">
                <a href="{{ route('onboarding.show', ['step' => 'company']) }}" class="auth-premium-back-link">Volver</a>
                <button type="submit" class="auth-premium-submit" style="width:auto;padding-left:1.5rem;padding-right:1.5rem;">
                    Continuar
                </button>
            </div>
        </form>
    </x-auth-premium-layout>
</x-layouts.auth>
