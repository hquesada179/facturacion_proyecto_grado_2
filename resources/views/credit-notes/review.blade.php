<x-layouts.app
    title="Revisar nota crédito"
    active="credit-notes"
    screen="credit_note.review"
    resource-type="credit_note"
    :resource-id="$creditNote->id"
>
    <x-page-header eyebrow="Notas crédito" :title="'Revisar nota crédito #'.$creditNote->id" description="Valida localmente y emite la nota crédito simulada.">
        <x-slot:actions>
            <x-ui.badge :status="$creditNote->status->label()" />
        </x-slot:actions>
    </x-page-header>

    @if ($validationErrors)
        <div class="mb-lg bg-[#fde8e8] border border-[#9b1c1c]/20 text-[#9b1c1c] rounded-lg p-md font-body-sm text-body-sm">
            <p class="font-label-md text-label-md mb-xs">Hay validaciones pendientes:</p>
            <ul class="list-disc list-inside">
                @foreach ($validationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="mb-lg bg-[#def7ec] border border-[#03543f]/20 text-[#03543f] rounded-lg p-md font-body-sm text-body-sm">
            La nota crédito está lista para validación o emisión simulada. Documento de prueba – sin validez tributaria.
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Factura origen</p><p class="font-title-lg text-title-lg">{{ $creditNote->invoice->number }}</p></div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Motivo</p><p class="font-title-lg text-title-lg">{{ $creditNote->reason }}</p></div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Impuestos</p><p class="font-title-lg text-title-lg font-mono">{{ \App\Support\ReportFormatter::money($creditNote->tax_total) }}</p></div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total acreditado</p><p class="font-title-lg text-title-lg font-mono">{{ \App\Support\ReportFormatter::money($creditNote->total) }}</p></div>
    </div>

    <x-ui.data-table :table="[
        'title' => 'Líneas acreditadas',
        'headers' => ['Código', 'Descripción', 'Cantidad', 'Base', 'Impuesto', 'Total'],
        'hideViewAll' => true,
        'rows' => $creditNote->items->map(fn ($item) => [
            $item->product_code ?? '-',
            $item->description,
            (string) $item->credited_quantity.' '.$item->unit,
            \App\Support\ReportFormatter::money($item->taxable_base),
            \App\Support\ReportFormatter::money($item->tax_total),
            \App\Support\ReportFormatter::money($item->line_total),
        ])->all(),
    ]" />

    <div class="flex flex-col sm:flex-row justify-between gap-sm mt-lg">
        <a href="{{ route('credit-notes.create', ['invoice_id' => $creditNote->invoice_id]) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">Crear otra nota</a>
        <div class="flex gap-sm justify-end">
            @can('update', $creditNote)
                @if ($creditNote->status !== \App\Enums\CreditNoteStatus::LocallyValidated)
                    <form method="POST" action="{{ route('credit-notes.validate', $creditNote) }}">
                        @csrf
                        <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            Validar localmente
                        </button>
                    </form>
                @endif
            @endcan
            @can('issue', $creditNote)
                <form method="POST" action="{{ route('credit-notes.issue', $creditNote) }}" onsubmit="return confirm('¿Emitir esta nota crédito simulada?');">
                    @csrf
                    <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        Emitir nota crédito
                    </button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
