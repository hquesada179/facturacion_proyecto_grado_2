@props(['table'])

<div class="surface-card overflow-hidden">
    <div class="p-lg border-b border-outline-variant/40 flex justify-between items-center gap-md">
        <div>
            <h3 class="font-title-lg text-title-lg text-on-surface">{{ $table['title'] }}</h3>
            @if (! empty($table['description']))
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">{{ $table['description'] }}</p>
            @endif
        </div>
        @if (! empty($table['viewAllHref']))
            <a href="{{ $table['viewAllHref'] }}" class="text-primary font-label-md text-label-md hover:underline shrink-0">{{ $table['viewAllLabel'] ?? 'Ver todos' }}</a>
        @elseif (empty($table['hideViewAll']))
            <button class="text-primary font-label-md text-label-md hover:underline shrink-0" type="button">Ver todos</button>
        @endif
    </div>
    {{--
        UI/UX 2.0-A follow-up: every <th>/<td> used to be whitespace-nowrap
        unconditionally, with generous px-lg padding and no hint to the
        browser about which columns could ever compress. A table's intrinsic
        (auto) layout gives a non-wrapping cell exactly its content's full
        width — stack six of those (customer name, motivo, etc.) and the
        table's natural width blows past the viewport, so the wrapper's
        overflow-x-auto kicks in even on a normal desktop screen.

        Fix: classify each cell the same way the component already does for
        styling (badge / money / plain text) and let ONLY plain-text cells
        wrap. Badges and money stay compact and unbroken (a status pill or
        "$ 283.220,00" must never wrap); free text (customer, motivo,
        description...) is allowed to wrap, so table-layout:auto can shrink
        those columns to fit the available width instead of forcing a
        horizontal scrollbar. Nothing about which data is shown changes.
    --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse {{ ! empty($table['colWidths']) ? 'table-fixed' : '' }}">
            @if (! empty($table['colWidths']))
                {{--
                    Opt-in only (customers/index.blade.php is the first
                    caller) — every other screen keeps the default
                    table-layout:auto/intrinsic-width behavior described
                    above untouched. table-layout:fixed + an explicit
                    <colgroup> is what actually guarantees zero horizontal
                    overflow regardless of content length: column widths
                    stop being computed from cell content at all, so a
                    long customer name or email is simply forced to wrap
                    inside its own fixed column instead of ever growing
                    the table past 100% of its container.
                --}}
                <colgroup>
                    @foreach ($table['colWidths'] as $width)
                        <col style="width: {{ $width }}">
                    @endforeach
                    <col style="width: {{ $table['actionsColWidth'] ?? '76px' }}">
                </colgroup>
            @endif
            <thead>
                <tr class="bg-surface-container-low border-b border-outline-variant/40">
                    @foreach ($table['headers'] as $header)
                        <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium align-middle whitespace-nowrap {{ $header === 'Estado' ? 'text-center' : '' }}">{{ $header }}</th>
                    @endforeach
                    <th class="font-label-sm text-label-sm text-on-surface-variant px-md py-sm font-medium text-center align-middle whitespace-nowrap">Acciones</th>
                </tr>
            </thead>
            <tbody class="font-body-sm text-body-sm">
                @forelse ($table['rows'] as $row)
                    @php($isStructuredRow = is_array($row) && array_key_exists('cells', $row))
                    <tr class="border-b border-outline-variant/40 last:border-b-0 hover:bg-surface-container-low transition-colors">
                        @foreach (($isStructuredRow ? $row['cells'] : $row) as $cell)
                            @php($isBadge = is_string($cell) && \App\Support\StatusTone::isKnown($cell))
                            @php($isMoney = is_string($cell) && str_starts_with($cell, '$'))
                            <td class="px-md py-sm text-on-surface-variant align-middle {{ $isBadge ? 'text-center whitespace-nowrap' : ($isMoney ? 'text-right whitespace-nowrap' : 'whitespace-normal break-words') }}">
                                @if ($isBadge)
                                    <x-ui.badge :status="$cell" />
                                @elseif ($isMoney)
                                    <span class="font-mono tabular-nums text-on-surface">{{ $cell }}</span>
                                @else
                                    {{ $cell }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-md py-sm text-center align-middle whitespace-nowrap">
                            @if ($isStructuredRow && ! empty($row['actions']))
                                <div class="flex items-center justify-center gap-xs">
                                    @foreach ($row['actions'] as $action)
                                        @if (($action['method'] ?? 'GET') === 'GET')
                                            <a href="{{ $action['href'] }}" class="icon-btn inline-flex text-on-surface-variant hover:text-primary hover:bg-surface-container-low p-xs rounded-lg" aria-label="{{ $action['label'] }}" title="{{ $action['label'] }}">
                                                <span class="material-symbols-outlined text-xl">{{ $action['icon'] ?? 'visibility' }}</span>
                                            </a>
                                        @else
                                            <form method="POST" action="{{ $action['href'] }}" @if(! empty($action['confirm'])) onsubmit="return confirm('{{ $action['confirm'] }}');" @endif>
                                                @csrf
                                                @if ($action['method'] !== 'POST')
                                                    @method($action['method'])
                                                @endif
                                                <button type="submit" class="icon-btn text-on-surface-variant hover:text-primary hover:bg-surface-container-low p-xs rounded-lg" aria-label="{{ $action['label'] }}" title="{{ $action['label'] }}">
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
