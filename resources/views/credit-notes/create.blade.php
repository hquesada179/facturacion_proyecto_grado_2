<x-layouts.app title="Crear nota crédito" active="credit-notes">
    <x-page-header eyebrow="Notas crédito" title="Crear nota crédito" description="Selecciona una factura emitida, motivo y líneas a acreditar." />

    @if ($invoice === null)
        <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <form method="GET" action="{{ route('credit-notes.create') }}" class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-sm">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Factura origen</span>
                    <select name="invoice_id" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        @foreach ($invoices as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->number }} · {{ $candidate->customer->name ?? '-' }} · ${{ number_format((float) $candidate->total, 2) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        Continuar
                    </button>
                </div>
            </form>
        </section>
    @else
        <div class="mb-lg bg-[#fdf6b2] border border-[#723b13]/20 text-[#723b13] rounded-lg p-md font-body-sm text-body-sm flex items-center gap-sm">
            <span class="material-symbols-outlined text-[18px]">science</span>
            Documento de prueba – sin validez tributaria. Conceptos internos marcados como prototipo.
        </div>

        <form method="POST" action="{{ route('credit-notes.store') }}" class="space-y-lg">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

            <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Factura origen</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-md font-body-sm text-body-sm">
                    <div><span class="text-on-surface-variant">Factura</span><br><strong>{{ $invoice->number }}</strong></div>
                    <div><span class="text-on-surface-variant">Cliente</span><br><strong>{{ $invoice->customer->name ?? ($invoice->customer_snapshot['name'] ?? '-') }}</strong></div>
                    <div><span class="text-on-surface-variant">Estado</span><br><x-ui.badge :status="$invoice->status->label()" /></div>
                    <div><span class="text-on-surface-variant">Total</span><br><strong class="font-mono">${{ number_format((float) $invoice->total, 2) }}</strong></div>
                </div>
            </section>

            <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Motivo</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <label>
                        <span class="block font-label-md text-label-md text-on-surface mb-xs">Concepto</span>
                        <select name="reason_code" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md" required>
                            @foreach ($reasons as $code => $label)
                                <option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="block font-label-md text-label-md text-on-surface mb-xs">Justificación</span>
                        <input name="reason_text" value="{{ old('reason_text') }}" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md" required>
                    </label>
                </div>
                <label class="block mt-md">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Notas internas</span>
                    <textarea name="notes" class="w-full min-h-24 rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">{{ old('notes') }}</textarea>
                </label>
            </section>

            <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Líneas a acreditar</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-surface-variant">
                                <th class="py-sm font-label-sm text-label-sm text-on-surface-variant">Código</th>
                                <th class="py-sm font-label-sm text-label-sm text-on-surface-variant">Descripción</th>
                                <th class="py-sm font-label-sm text-label-sm text-on-surface-variant">Facturado</th>
                                <th class="py-sm font-label-sm text-label-sm text-on-surface-variant">Disponible</th>
                                <th class="py-sm font-label-sm text-label-sm text-on-surface-variant">Cantidad a acreditar</th>
                            </tr>
                        </thead>
                        <tbody class="font-body-sm text-body-sm">
                            @foreach ($invoice->items as $item)
                                <tr class="border-b border-surface-variant last:border-b-0">
                                    <td class="py-sm font-mono">{{ $item->product_code ?? '-' }}</td>
                                    <td class="py-sm">{{ $item->description }}</td>
                                    <td class="py-sm">{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td class="py-sm">{{ $availableByItem[$item->id] ?? '0' }}</td>
                                    <td class="py-sm">
                                        <input name="items[{{ $item->id }}][quantity]" value="{{ old('items.'.$item->id.'.quantity') }}" class="w-32 rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md" type="number" step="0.01" min="0" max="{{ $availableByItem[$item->id] ?? 0 }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex justify-between gap-sm">
                <a href="{{ route('invoices.show', $invoice) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">Volver a factura</a>
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                    <span class="material-symbols-outlined text-[18px]">rate_review</span>
                    Revisar nota crédito
                </button>
            </div>
        </form>
    @endif
</x-layouts.app>
