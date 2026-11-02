{{--
    Register/verify brand-panel step list — same component shape as
    <x-onboarding.step-list>, just a different fixed step map, so the
    register → verify-email screens reuse the exact
    auth-premium-wizard-steps/-step/.dot/.done/.current CSS already
    approved for onboarding instead of a parallel visual language.
--}}
@props(['current'])

@php
    $steps = [
        'landing' => 'Landing',
        'register' => 'Crear cuenta',
        'verify' => 'Verificar correo',
        'onboarding' => 'Configurar Fiscora',
    ];
    $keys = array_keys($steps);
    // "Landing" is always already behind the visitor by the time this
    // renders, so the lowest valid current step is 'register'.
    $currentIndex = array_search($current, $keys, true);
    $currentIndex = $currentIndex === false ? 1 : $currentIndex;
@endphp

<div class="auth-premium-visual" data-reveal style="transition-delay:300ms">
    <nav class="auth-premium-wizard-steps" aria-label="Progreso de registro">
        @foreach ($steps as $key => $label)
            @php($index = array_search($key, $keys, true))
            <div
                class="auth-premium-wizard-step {{ $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : '') }}"
                @if ($index === $currentIndex) aria-current="step" @endif
            >
                <span class="dot">
                    @if ($index < $currentIndex)
                        <span class="material-symbols-outlined text-[14px]">check</span>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </nav>
</div>
