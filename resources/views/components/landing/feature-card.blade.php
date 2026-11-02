@props(['title', 'tone' => 'light'])

{{--
    Icon + title + description card — reused across Funcionalidades,
    Producto and Seguridad (via `tone="dark"` for the navy band). Icons
    are raw inline SVG passed through the `icon` slot (matching the
    approved artifact's thin-line icon set), not Material Symbols —
    deliberately a different, smaller icon language from the
    authenticated app's sidebar/topbar, kept local to the landing only.
--}}
@php
    $isDark = $tone === 'dark';
@endphp
<article
    data-reveal
    class="landing-card rounded-2xl p-lg h-full {{ $isDark
        ? 'bg-white/5 border border-white/14 hover:border-[var(--landing-gold-soft)]'
        : 'bg-white border border-[color-mix(in_srgb,var(--color-on-surface)_7%,transparent)] hover:shadow-[0_26px_50px_-22px_rgba(10,16,32,0.22)] hover:border-transparent' }}"
>
    <div class="w-11 h-11 rounded-[10px] mb-md flex items-center justify-center {{ $isDark
        ? 'bg-white/10 text-[var(--landing-gold-soft)]'
        : 'bg-primary/9 text-primary' }}">
        {{ $icon }}
    </div>
    <h3 class="font-title-lg text-title-lg mb-xs {{ $isDark ? 'text-white' : 'text-on-surface' }}">{{ $title }}</h3>
    <p class="font-body-sm text-body-sm {{ $isDark ? 'text-white/70' : 'text-on-surface-variant' }}">{{ $slot }}</p>
</article>
