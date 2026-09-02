@props(['eyebrow' => null, 'title', 'description' => null])

<div class="mb-lg">
    @if ($eyebrow)
        <p class="font-label-sm text-label-sm text-primary uppercase mb-xs">{{ $eyebrow }}</p>
    @endif
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-md">
        <div>
            <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface">{{ $title }}</h2>
            @if ($description)
                <p class="font-body-md text-body-md text-on-surface-variant mt-xs">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex items-center gap-sm">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
