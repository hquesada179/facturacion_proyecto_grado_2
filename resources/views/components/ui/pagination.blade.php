@props(['paginator'])

@if ($paginator->hasPages())
    <div class="px-lg py-md border-t border-surface-variant flex items-center justify-between gap-md font-body-sm text-body-sm text-on-surface-variant">
        <span>Mostrando {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} de {{ $paginator->total() }}</span>
        <div class="flex gap-xs">
            @if ($paginator->onFirstPage())
                <span class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant/50">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary">Siguiente</a>
            @else
                <span class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant/50">Siguiente</span>
            @endif
        </div>
    </div>
@endif
