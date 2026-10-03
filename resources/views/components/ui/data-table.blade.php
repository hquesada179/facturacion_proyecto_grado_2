@props(['table'])

<div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/40">
    <div class="p-lg border-b border-surface-variant flex justify-between items-center">
        <h3 class="font-title-lg text-title-lg text-on-surface">{{ $table['title'] }}</h3>
        @unless (! empty($table['hideViewAll']))
            <button class="text-primary font-label-md text-label-md hover:underline" type="button">Ver todos</button>
        @endunless
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
                @forelse ($table['rows'] as $row)
                    @php($isStructuredRow = is_array($row) && array_key_exists('cells', $row))
                    <tr class="border-b border-surface-variant last:border-b-0 hover:bg-surface-container-low transition-colors">
                        @foreach (($isStructuredRow ? $row['cells'] : $row) as $cell)
                            <td class="px-lg py-md text-on-surface-variant whitespace-nowrap">
                                @if (in_array($cell, ['Validada', 'Pendiente', 'Con error', 'Anulada', 'Activo', 'Por validar', 'Finalizada', 'En revisión', 'Borrador', 'Correcto', 'Aprobada', 'Registrada', 'Respondida', 'Inactivo', 'Descontinuado', 'Vigente', 'Por vencer', 'Agotada', 'Inactiva'], true))
                                    <x-ui.badge :status="$cell" />
                                @elseif (is_string($cell) && str_starts_with($cell, '$'))
                                    <span class="font-mono text-on-surface">{{ $cell }}</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-lg py-md text-center">
                            @if ($isStructuredRow && ! empty($row['actions']))
                                <div class="flex items-center justify-center gap-xs">
                                    @foreach ($row['actions'] as $action)
                                        @if (($action['method'] ?? 'GET') === 'GET')
                                            <a href="{{ $action['href'] }}" class="text-on-surface-variant hover:text-primary p-xs rounded-lg" aria-label="{{ $action['label'] }}" title="{{ $action['label'] }}">
                                                <span class="material-symbols-outlined text-xl">{{ $action['icon'] ?? 'visibility' }}</span>
                                            </a>
                                        @else
                                            <form method="POST" action="{{ $action['href'] }}" @if(! empty($action['confirm'])) onsubmit="return confirm('{{ $action['confirm'] }}');" @endif>
                                                @csrf
                                                @if ($action['method'] !== 'POST')
                                                    @method($action['method'])
                                                @endif
                                                <button type="submit" class="text-on-surface-variant hover:text-primary p-xs rounded-lg" aria-label="{{ $action['label'] }}" title="{{ $action['label'] }}">
                                                    <span class="material-symbols-outlined text-xl">{{ $action['icon'] ?? 'edit' }}</span>
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <button class="text-on-surface-variant hover:text-primary" type="button" aria-label="Más acciones">
                                    <span class="material-symbols-outlined text-xl">more_vert</span>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($table['headers']) + 1 }}" class="px-lg py-xl text-center text-on-surface-variant font-body-sm text-body-sm">
                            {{ $table['emptyMessage'] ?? 'No hay registros para mostrar.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @isset($table['paginator'])
        <x-ui.pagination :paginator="$table['paginator']" />
    @endisset
</div>
