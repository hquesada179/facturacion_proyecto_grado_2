<x-layouts.auth title="Onboarding — Completado">
    <x-auth-premium-layout>
        <x-slot:brandVisual>
            <x-onboarding.step-list current="done" />
        </x-slot:brandVisual>

        <div class="text-center" data-reveal>
            <div class="onboarding-complete-icon">
                <span class="material-symbols-outlined">check_circle</span>
            </div>
            <h2 class="auth-premium-form-title">Tu espacio está listo</h2>
            <p class="auth-premium-form-subtitle mb-lg">Ahora te mostraremos cómo crear tu primera factura en Fiscora.</p>

            <div class="onboarding-summary-list" data-reveal style="transition-delay:80ms">
                <div class="onboarding-summary-item">
                    <span class="ic"><span class="material-symbols-outlined">check</span></span>
                    <span><span class="t">Empresa configurada</span><span class="s">{{ $company->legal_name }}</span></span>
                </div>
                <div class="onboarding-summary-item">
                    <span class="ic"><span class="material-symbols-outlined">check</span></span>
                    <span><span class="t">Cliente creado</span><span class="s">{{ $customer?->name ?? 'Sin cliente' }}</span></span>
                </div>
                <div class="onboarding-summary-item">
                    <span class="ic"><span class="material-symbols-outlined">check</span></span>
                    <span><span class="t">Producto creado</span><span class="s">{{ $product?->name ?? 'Sin producto' }}</span></span>
                </div>
                <div class="onboarding-summary-item">
                    <span class="ic"><span class="material-symbols-outlined">check</span></span>
                    <span><span class="t">Facturación preparada</span><span class="s">{{ $company->currency ?? 'COP' }}{{ $company->mainTax ? ' · '.$company->mainTax->name : '' }}</span></span>
                </div>
            </div>

            <div class="flex flex-col items-center gap-sm" data-reveal style="transition-delay:120ms">
                <form method="POST" action="{{ route('onboarding.complete') }}">
                    @csrf
                    <input type="hidden" name="redirect" value="tour">
                    <button type="submit" class="auth-premium-submit" style="width:auto;padding-left:1.75rem;padding-right:1.75rem;">
                        Comenzar tutorial
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('onboarding.complete') }}">
                    @csrf
                    <button type="submit" class="auth-premium-back-link" style="background:none;border:none;cursor:pointer;">Ir al dashboard</button>
                </form>
            </div>

            <div class="auth-premium-mini-card mt-xl text-left" data-reveal style="transition-delay:160ms">
                <p class="font-title-lg text-title-lg text-on-surface mb-xs">¿Necesitas ayuda?</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-md">El Asistente IA puede orientarte según la pantalla y el documento que estés consultando. Estos son algunos ejemplos de lo que puedes preguntarle:</p>
                <ul class="font-body-sm text-body-sm text-on-surface-variant mb-md" style="list-style:disc;padding-left:1.25rem;line-height:1.7;">
                    <li>¿Cómo creo una factura?</li>
                    <li>¿Qué significa un borrador?</li>
                    <li>¿Cómo corrijo una validación?</li>
                    <li>¿Cómo hago una nota crédito?</li>
                    <li>¿Dónde veo la trazabilidad?</li>
                </ul>
                @can('use-assistant')
                    <a href="{{ route('assistant.index') }}" class="auth-premium-back-link" style="font-weight:700;">
                        <span class="material-symbols-outlined text-[16px]">auto_awesome</span>
                        Abrir Asistente IA
                    </a>
                @endcan
            </div>
        </div>
    </x-auth-premium-layout>
</x-layouts.auth>
