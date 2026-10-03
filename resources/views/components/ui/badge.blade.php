@props(['status'])

@php
    $classes = match ($status) {
        'Validada', 'Activo', 'Correcto', 'Aprobada', 'Registrada', 'Respondida', 'Finalizada', 'Vigente', 'Emitida', 'Validada localmente' => 'bg-[#def7ec] text-[#03543f]',
        'Pendiente', 'Por validar', 'En revisión', 'Borrador', 'Por vencer', 'Enviando (simulado)' => 'bg-[#fdf6b2] text-[#723b13]',
        'Con error', 'Agotada', 'Rechazada (simulado)', 'Error técnico' => 'bg-[#fde8e8] text-[#9b1c1c]',
        'Anulada', 'Inactivo', 'Inactiva', 'Descontinuado', 'Descartada', 'Parcialmente abonada' => 'bg-surface-variant text-on-surface-variant',
        default => 'bg-surface-container text-on-surface-variant',
    };
@endphp

<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $classes }}">{{ $status }}</span>
