<x-layouts.app title="{{ $invoice->number ?? 'Borrador de factura' }}" active="invoices">
    <x-page-header eyebrow="Facturas" :title="$invoice->number ?? 'Borrador de factura #'.$invoice->id" description="Consulta del documento, productos y trazabilidad.">
        <x-slot:actions>
            <x-ui.badge :status="$invoice->status->label()" />
        </x-slot:actions>
    </x-page-header>

    @if (\App\Services\Invoices\InvoiceStateMachine::isEditable($invoice->status))
        <div class="mb-lg bg-[#fdf6b2] border border-[#723b13]/20 text-[#723b13] rounded-lg p-md font-body-sm text-body-sm flex items-center justify-between gap-sm">
            <span>Esta factura sigue en borrador y aún puede editarse.</span>
            <a href="{{ route('invoices.draft.validation', $invoice) }}" class="font-label-md text-label-md underline">Continuar editando</a>
        </div>
    @endif

    @if ($invoice->status->value === 'issued')
        <div class="mb-lg bg-[#def7ec] border border-[#03543f]/20 text-[#03543f] rounded-lg p-md font-body-sm text-body-sm">
            <p class="font-label-md text-label-md flex items-center gap-sm mb-xs">
                <span class="material-symbols-outlined text-[18px]">science</span>
                Validación simulada — Documento de prueba, sin validez tributaria.
            </p>
            <p>CUFE simulado: <span class="font-mono break-all">{{ $invoice->simulated_cufe }}</span></p>
        </div>
    @elseif (in_array($invoice->status->value, ['simulated_rejected', 'technical_error'], true))
        <div class="mb-lg bg-[#fde8e8] border border-[#9b1c1c]/20 text-[#9b1c1c] rounded-lg p-md font-body-sm text-body-sm">
            {{ $invoice->dian_simulation_message }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Cliente</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ $invoice->customer->name ?? '-' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Fecha de emisión</p>
            <p class="font-title-lg text-title-lg text-on-surface">{{ optional($invoice->issued_at)->format('Y-m-d H:i') ?? 'Sin emitir' }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Estado</p>
            <x-ui.badge :status="$invoice->status->label()" />
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ number_format((float) $invoice->total, 2) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-lg">
        <x-ui.data-table :table="[
            'title' => 'Productos y servicios',
            'headers' => ['Descripción', 'Cantidad', 'Valor unitario', 'Impuestos', 'Total línea'],
            'hideViewAll' => true,
            'emptyMessage' => 'Esta factura no tiene líneas.',
            'rows' => $invoice->items->map(fn ($item) => [
                $item->description,
                (string) $item->quantity,
                '$'.number_format((float) $item->unit_price, 2),
                $item->itemTaxes->pluck('code')->implode(', ') ?: 'Sin impuesto',
                '$'.number_format((float) $item->line_total, 2),
            ])->all(),
        ]" />

        <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Trazabilidad</h3>
            <div class="space-y-md">
                @forelse ($invoice->events as $event)
                    <div class="flex gap-sm">
                        <div class="w-9 h-9 rounded-full bg-primary-fixed text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[18px]">history</span>
                        </div>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface">
                                {{ $event->description }}
                                <span class="text-on-surface-variant">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                            </p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">
                                {{ $event->user?->name ?? 'Sistema' }} · {{ $event->type }}
                                @if ($event->from_status || $event->to_status)
                                    · {{ $event->from_status ?? '—' }} → {{ $event->to_status ?? '—' }}
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Sin eventos registrados.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
