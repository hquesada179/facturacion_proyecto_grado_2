@props([
    'tone' => 'info',
    'icon' => null,
    'title' => null,
])

{{--
    Unified status/alert surface — info, success, warning, error, neutral.
    One place defining "what a banner/notice looks like" so every
    validation summary, document-status notice and flash message in the
    app draws from the same five tones instead of ad hoc raw hex. Pure
    presentation: tone only ever selects a palette + default icon, it
    never changes what is actually shown.
--}}
@php
    $tones = [
        'info' => [
            'bg' => 'bg-primary-fixed/50',
            'border' => 'border-primary-fixed-dim',
            'icon_bg' => 'bg-primary-fixed',
            'icon_text' => 'text-on-primary-fixed-variant',
            'title_text' => 'text-on-primary-fixed',
            'body_text' => 'text-on-primary-fixed/85',
            'icon' => 'info',
        ],
        'success' => [
            'bg' => 'bg-success-soft/60',
            'border' => 'border-success-soft-foreground/20',
            'icon_bg' => 'bg-success-soft',
            'icon_text' => 'text-success-soft-foreground',
            'title_text' => 'text-success-soft-foreground',
            'body_text' => 'text-success-soft-foreground/85',
            'icon' => 'check_circle',
        ],
        'warning' => [
            'bg' => 'bg-warning-soft/60',
            'border' => 'border-warning-soft-foreground/20',
            'icon_bg' => 'bg-warning-soft',
            'icon_text' => 'text-warning-soft-foreground',
            'title_text' => 'text-warning-soft-foreground',
            'body_text' => 'text-warning-soft-foreground/85',
            'icon' => 'warning',
        ],
        'error' => [
            'bg' => 'bg-error-soft/60',
            'border' => 'border-error-soft-foreground/20',
            'icon_bg' => 'bg-error-soft',
            'icon_text' => 'text-error-soft-foreground',
            'title_text' => 'text-error-soft-foreground',
            'body_text' => 'text-error-soft-foreground/85',
            'icon' => 'error',
        ],
        'neutral' => [
            'bg' => 'bg-neutral-soft',
            'border' => 'border-outline-variant/50',
            'icon_bg' => 'bg-surface-container',
            'icon_text' => 'text-on-surface-variant',
            'title_text' => 'text-on-surface',
            'body_text' => 'text-on-surface-variant',
            'icon' => 'science',
        ],
    ];
    $t = $tones[$tone] ?? $tones['info'];
    $resolvedIcon = $icon ?? $t['icon'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border p-md flex items-start gap-sm '.$t['bg'].' '.$t['border']]) }}>
    <span class="w-8 h-8 rounded-full {{ $t['icon_bg'] }} {{ $t['icon_text'] }} flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-[18px]">{{ $resolvedIcon }}</span>
    </span>

    <div class="flex-1 min-w-0 font-body-sm text-body-sm {{ $t['body_text'] }}">
        @if ($title)
            <p class="font-label-md text-label-md font-semibold mb-xs {{ $t['title_text'] }}">{{ $title }}</p>
        @endif

        {{ $slot }}

        @isset($footer)
            <div class="mt-sm">{{ $footer }}</div>
        @endisset
    </div>
</div>
