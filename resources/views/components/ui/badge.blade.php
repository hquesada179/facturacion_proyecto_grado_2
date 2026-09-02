@props(['status'])

@php
    $classes = match ($status) {
        'Validada', 'Activo', 'Correcto', 'Aprobada', 'Registrada', 'Respondida', 'Finalizada' => 'bg-[#def7ec] text-[#03543f]',
        'Pendiente', 'Por validar', 'En revisión', 'Borrador' => 'bg-[#fdf6b2] text-[#723b13]',
        'Con error' => 'bg-[#fde8e8] text-[#9b1c1c]',
        'Anulada' => 'bg-surface-variant text-on-surface-variant',
        default => 'bg-surface-container text-on-surface-variant',
    };
@endphp

<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $classes }}">{{ $status }}</span>
