@props(['table'])

<div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/40">
    <div class="p-lg border-b border-surface-variant flex justify-between items-center">
        <h3 class="font-title-lg text-title-lg text-on-surface">{{ $table['title'] }}</h3>
        <button class="text-primary font-label-md text-label-md hover:underline" type="button">Ver todos</button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-lowest border-b border-surface-variant">
                    @foreach ($table['headers'] as $header)
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium whitespace-nowrap">{{ $header }}</th>
                    @endforeach
                    <th class="font-label-sm text-label-sm text-on-surface-variant px-lg py-sm font-medium text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="font-body-sm text-body-sm">
                @foreach ($table['rows'] as $row)
                    <tr class="border-b border-surface-variant last:border-b-0 hover:bg-surface-container-low transition-colors">
                        @foreach ($row as $cell)
                            <td class="px-lg py-md text-on-surface-variant whitespace-nowrap">
                                @if (in_array($cell, ['Validada', 'Pendiente', 'Con error', 'Anulada', 'Activo', 'Por validar', 'Finalizada', 'En revisión', 'Borrador', 'Correcto', 'Aprobada', 'Registrada', 'Respondida'], true))
                                    <x-ui.badge :status="$cell" />
                                @elseif (is_string($cell) && str_starts_with($cell, '$'))
                                    <span class="font-mono text-on-surface">{{ $cell }}</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-lg py-md text-center">
                            <button class="text-on-surface-variant hover:text-primary" type="button" aria-label="Más acciones">
                                <span class="material-symbols-outlined text-xl">more_vert</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
