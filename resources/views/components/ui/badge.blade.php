@props(['status'])

@php($classes = \App\Support\StatusTone::classes($status))

<span class="inline-flex items-center justify-center px-sm py-xs rounded-full font-label-sm text-label-sm leading-none align-middle {{ $classes }}">{{ $status }}</span>
