@props(['question'])

{{--
    Native <details>/<summary> — opens and closes with zero JavaScript,
    so it can never end up half-broken if landing.js fails to load.
    Only the chevron icon rotates (see app.css's `.landing-faq` rules);
    the panel's show/hide is the browser's own display toggle, not an
    animated height.
--}}
<div class="landing-faq border-b border-[color-mix(in_srgb,var(--color-on-surface)_10%,transparent)]">
    <details>
        <summary class="w-full flex items-center justify-between gap-md py-md font-title-lg text-title-lg text-on-surface">
            <span>{{ $question }}</span>
            <span class="material-symbols-outlined landing-faq-chevron text-primary shrink-0">expand_more</span>
        </summary>
        <p class="font-body-md text-body-md text-on-surface-variant pb-md max-w-[60ch]">{{ $slot }}</p>
    </details>
</div>
