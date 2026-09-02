@props(['name', 'fill' => false])

<span {{ $attributes->merge(['class' => 'material-symbols-outlined']) }} @if($fill) data-fill="true" @endif>{{ $name }}</span>
