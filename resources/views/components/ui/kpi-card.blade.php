@props([
    'label',
    'value',
    'icon' => 'insights',
    'tone' => 'primary',
])

@php
    $toneClasses = [
        'primary' => ['bg' => 'bg-primary/10', 'text' => 'text-primary', 'border' => 'border-primary'],
        'secondary' => ['bg' => 'bg-secondary-container/20', 'text' => 'text-secondary', 'border' => 'border-secondary'],
        'warning' => ['bg' => 'bg-tertiary-fixed-dim/20', 'text' => 'text-tertiary-container', 'border' => 'border-tertiary-fixed-dim'],
        'error' => ['bg' => 'bg-error-container/50', 'text' => 'text-error', 'border' => 'border-error'],
    ][$tone] ?? ['bg' => 'bg-primary/10', 'text' => 'text-primary', 'border' => 'border-primary'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-surface-container-lowest rounded-xl p-lg shadow-soft flex flex-col justify-between border-l-4 min-h-32 '.$toneClasses['border']]) }}>
    <div class="w-10 h-10 rounded-full {{ $toneClasses['bg'] }} flex items-center justify-center {{ $toneClasses['text'] }} mb-md">
        <span class="material-symbols-outlined">{{ $icon }}</span>
    </div>
    <div>
        <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">{{ $label }}</p>
        <p class="font-title-lg text-title-lg text-on-surface">{{ $value }}</p>
    </div>
</div>
