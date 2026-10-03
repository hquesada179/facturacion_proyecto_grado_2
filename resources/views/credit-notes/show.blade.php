<x-layouts.app
    title="{{ $creditNote->number ?? 'Nota crédito' }}"
    active="credit-notes"
    screen="credit_note.show"
    resource-type="credit_note"
    :resource-id="$creditNote->id"
>
    <x-page-header eyebrow="Notas crédito" :title="$creditNote->number ?? 'Nota crédito #'.$creditNote->id" description="Consulta de nota crédito simulada y su relación con la factura origen.">
        <x-slot:actions>
            @if ($creditNote->status === \App\Enums\CreditNoteStatus::Issued)
                @can('downloadPdf', $creditNote)
                    <a href="{{ route('credit-notes.pdf.show', $creditNote) }}" target="_blank" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md flex items-center gap-sm">
                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                        Ver PDF
                    </a>
                    <a href="{{ route('credit-notes.pdf.download', $creditNote) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md flex items-center gap-sm">
                        <span class="material-symbols-outlined text-[18px]">download</span>
                        Descargar PDF
                    </a>
                @endcan
            @endif
            <a href="{{ route('invoices.show', $creditNote->invoice) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md flex items-center gap-sm">
                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                Ver factura
            </a>
            <x-ui.badge :status="$creditNote->status->label()" />
        </x-slot:actions>
    </x-page-header>

    <div class="mb-lg bg-[#fdf6b2] border border-[#723b13]/20 text-[#723b13] rounded-lg p-md font-body-sm text-body-sm">
        Documento de prueba – sin validez tributaria. Nota crédito generada en flujo académico simulado.
        @if ($creditNote->simulated_cufe)
            <br>CUFE/identificador simulado: <span class="font-mono break-all">{{ $creditNote->simulated_cufe }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Factura origen</p><p class="font-title-lg text-title-lg">{{ $creditNote->invoice->number }}</p></div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Cliente</p><p class="font-title-lg text-title-lg">{{ $creditNote->invoice->customer->name ?? ($creditNote->invoice->customer_snapshot['name'] ?? '-') }}</p></div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Motivo</p><p class="font-title-lg text-title-lg">{{ $creditNote->reason }}</p></div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40"><p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total acreditado</p><p class="font-title-lg text-title-lg font-mono">${{ number_format((float) $creditNote->total, 2) }}</p></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-lg">
        <x-ui.data-table :table="[
            'title' => 'Líneas acreditadas',
            'headers' => ['Código', 'Descripción', 'Cantidad', 'Base', 'Impuesto', 'Total'],
            'hideViewAll' => true,
            'rows' => $creditNote->items->map(fn ($item) => [
                $item->product_code ?? '-',
                $item->description,
                (string) $item->credited_quantity.' '.$item->unit,
                '$'.number_format((float) $item->taxable_base, 2),
                '$'.number_format((float) $item->tax_total, 2),
                '$'.number_format((float) $item->line_total, 2),
            ])->all(),
        ]" />

        @can('view-traceability')
            <section id="trazabilidad" class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Trazabilidad</h3>
                <div class="space-y-md">
                    @foreach ($creditNote->invoice->events->filter(fn ($event) => ($event->metadata['credit_note_id'] ?? null) === $creditNote->id || in_array($event->type, ['invoice_partially_credited', 'invoice_voided'], true)) as $event)
                        <div class="flex gap-sm">
                            <div class="w-9 h-9 rounded-full bg-primary-fixed text-primary flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">history</span>
                            </div>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface">{{ $event->description }}</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $event->user?->name ?? 'Sistema' }} · {{ $event->type }} · {{ $event->created_at->format('Y-m-d H:i') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endcan
    </div>
</x-layouts.app>
