@props([
    'severity',
    'rule',
    'code',
    'message',
    'field' => null,
    'suggestion' => null,
    'actionHref' => null,
    'actionLabel' => null,
])

{{--
    One ValidationEngine result (ValidationResult->regla/codigo/mensaje/
    campo/sugerencia) rendered as a compact, scannable card — built on
    top of <x-ui.alert> so it shares the exact same tone palette as
    every other alert in the app, just with the extra "go fix it"
    footer link this specific use case needs. Severity values
    ('bloqueo'/'advertencia'/anything else) are exactly the ones
    ValidationEngine already produces — this component only maps them
    to a tone, it never changes what severity a rule reports.
--}}
@php
    $tone = match ($severity) {
        'bloqueo' => 'error',
        'advertencia' => 'warning',
        default => 'success',
    };
@endphp

<x-ui.alert :tone="$tone" :title="$rule.' ('.$code.')'">
    <p>{{ $message }}</p>

    @if ($field || $suggestion)
        <p class="mt-xs opacity-80">
            @if ($field)
                Campo: {{ $field }}
            @endif
            @if ($field && $suggestion)
                —
            @endif
            {{ $suggestion }}
        </p>
    @endif

    <x-slot:footer>
        @if ($actionHref)
            <a href="{{ $actionHref }}" class="inline-flex items-center gap-xs font-label-sm text-label-sm underline hover:no-underline">
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                {{ $actionLabel }}
            </a>
        @endif
    </x-slot:footer>
</x-ui.alert>
