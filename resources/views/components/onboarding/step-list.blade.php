@props(['current'])

@php
    $steps = [
        'company' => 'Empresa',
        'billing' => 'Facturación',
        'data' => 'Cliente y producto',
        'done' => 'Completado',
    ];
    $keys = array_keys($steps);
    $currentIndex = array_search($current, $keys, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
@endphp

<div class="auth-premium-visual" data-reveal style="transition-delay:300ms">
    <nav class="auth-premium-wizard-steps" aria-label="Progreso de configuración">
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
