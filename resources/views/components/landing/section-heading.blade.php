@props(['eyebrow' => null, 'title', 'description' => null, 'tone' => 'light'])

{{--
    Left-aligned eyebrow/title/description header — matches the approved
    artifact's editorial composition (deliberately not centered, unlike
    a generic marketing-page default). Reused by every landing section.

    Width is an explicit `max-w-[40rem]` (the artifact's own 640px),
    never a bare `max-w-xl` token class: Tailwind v4 resolves `max-w-xl`
    against Fiscora's own `--spacing-xl` (32px, from app.css's spacing
    scale) before it falls back to the built-in 36rem container value —
    the two scales share the "xl" key. That collision is what previously
    collapsed this heading into a ~32px column and forced the title to
    wrap one word per line.
--}}
@php
    $isDark = $tone === 'dark';
@endphp
<div data-reveal class="max-w-[40rem] mb-xl">
    @if ($eyebrow)
        <p class="font-label-sm text-label-sm uppercase tracking-[0.14em] mb-xs {{ $isDark ? 'text-[var(--landing-gold-soft)]' : 'text-primary' }}">{{ $eyebrow }}</p>
    @endif

    <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-balance mb-sm {{ $isDark ? 'text-white' : 'text-on-surface' }}">
        {{ $title }}
    </h2>

    @if ($description)
        <p class="font-body-md text-body-md max-w-[36ch] {{ $isDark ? 'text-white/75' : 'text-on-surface-variant' }}">{{ $description }}</p>
    @endif
</div>
