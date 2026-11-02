@props(['status'])

@php
    $classes = match ($status) {
        'Validada', 'Activo', 'Correcto', 'Aprobada', 'Registrada', 'Respondida', 'Finalizada', 'Vigente', 'Emitida', 'Validada localmente' => 'bg-success-soft text-success-soft-foreground',
        'Pendiente', 'Por validar', 'En revisión', 'Borrador', 'Por vencer', 'Enviando (simulado)' => 'bg-warning-soft text-warning-soft-foreground',
        'Con error', 'Agotada', 'Rechazada (simulado)', 'Error técnico' => 'bg-error-soft text-error-soft-foreground',
        'Anulada', 'Inactivo', 'Inactiva', 'Descontinuado', 'Descartada', 'Parcialmente abonada' => 'bg-surface-variant text-on-surface-variant',
        default => 'bg-surface-container text-on-surface-variant',
    };
@endphp

<span class="inline-flex items-center px-sm py-xs rounded-full font-label-sm text-label-sm {{ $classes }}">{{ $status }}</span>
