@props([
    'label',
    'value',
    'icon' => 'insights',
    'tone' => 'primary',
    'featured' => false,
])

@php
    $toneClasses = [
        'primary' => ['bg' => 'bg-primary/10', 'text' => 'text-primary'],
        'secondary' => ['bg' => 'bg-secondary-container/20', 'text' => 'text-secondary'],
        'warning' => ['bg' => 'bg-tertiary-fixed-dim/20', 'text' => 'text-tertiary-container'],
        'error' => ['bg' => 'bg-error-container/50', 'text' => 'text-error'],
    ][$tone] ?? ['bg' => 'bg-primary/10', 'text' => 'text-primary'];
@endphp

<div {{ $attributes->merge(['class' => 'surface-card surface-card-interactive '.($featured ? 'kpi-card-featured ' : '').'p-md flex flex-col gap-sm min-h-28']) }}>
    <div class="flex items-center gap-sm">
        <div class="w-8 h-8 rounded-lg {{ $toneClasses['bg'] }} flex items-center justify-center {{ $toneClasses['text'] }} shrink-0">
            <span class="material-symbols-outlined text-[16px]">{{ $icon }}</span>
        </div>
        <p class="font-label-sm text-label-sm text-on-surface-variant leading-tight">{{ $label }}</p>
    </div>
    <p class="font-headline-md text-headline-md text-on-surface leading-none tabular-nums">{{ $value }}</p>
</div>
