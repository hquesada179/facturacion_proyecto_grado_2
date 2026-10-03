<x-layouts.app title="Nueva factura" active="invoices">
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Revisa subtotales, impuestos y observaciones con datos reales del borrador." />

    <x-invoice-stepper :current="3" :invoice="$invoice" />

    @php
        $taxesByCode = [];
        foreach ($invoice->items as $item) {
            foreach ($item->itemTaxes as $itemTax) {
                $taxesByCode[$itemTax->code] ??= ['name' => $itemTax->name, 'base' => 0, 'value' => 0];
                $taxesByCode[$itemTax->code]['base'] += (float) $itemTax->base;
                $taxesByCode[$itemTax->code]['value'] += (float) $itemTax->value;
            }
        }
        $totalDiscounts = $invoice->items->sum(fn ($item) => (float) $item->discount_total);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Subtotal</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ number_format((float) $invoice->subtotal, 2) }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Descuentos</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ number_format($totalDiscounts, 2) }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total impuestos</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ number_format((float) $invoice->tax_total, 2) }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total a pagar</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ number_format((float) $invoice->total, 2) }}</p>
        </div>
    </div>

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Impuestos por tributo</h3>
        @if (count($taxesByCode) > 0)
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-surface-variant">
                        <th class="font-label-sm text-label-sm text-on-surface-variant py-sm">Tributo</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant py-sm">Base gravable</th>
                        <th class="font-label-sm text-label-sm text-on-surface-variant py-sm">Valor</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm">
                    @foreach ($taxesByCode as $code => $data)
                        <tr class="border-b border-surface-variant last:border-b-0">
                            <td class="py-sm text-on-surface">{{ $code }} — {{ $data['name'] }}</td>
                            <td class="py-sm font-mono text-on-surface-variant">${{ number_format($data['base'], 2) }}</td>
                            <td class="py-sm font-mono text-on-surface-variant">${{ number_format($data['value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="font-body-sm text-body-sm text-on-surface-variant">Esta factura no tiene impuestos aplicados.</p>
        @endif
    </section>

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Datos de la factura</h3>
        <form method="POST" action="{{ route('invoices.draft.summary.update', $invoice) }}" class="space-y-md">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Moneda</span>
                    <select name="currency" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        @foreach (['COP', 'USD'] as $currency)
                            <option value="{{ $currency }}" @selected($invoice->currency === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Forma de pago</span>
                    <select name="payment_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        <option value="">Sin definir</option>
                        <option value="contado" @selected($invoice->payment_type === 'contado')>Contado</option>
                        <option value="credito" @selected($invoice->payment_type === 'credito')>Crédito</option>
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Fecha de vencimiento</span>
                    <input name="due_date" value="{{ optional($invoice->due_date)->format('Y-m-d') }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="date">
                </label>
            </div>
            <label>
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Observaciones</span>
                <textarea name="notes" class="w-full min-h-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">{{ $invoice->notes }}</textarea>
            </label>
            <div class="flex justify-end">
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Guardar
                </button>
            </div>
        </form>
    </section>

    <x-ui.data-table :table="[
        'title' => 'Productos y servicios agregados',
        'headers' => ['Descripción', 'Cantidad', 'Valor unitario', 'Descuento', 'Total línea'],
        'hideViewAll' => true,
        'emptyMessage' => 'Esta factura todavía no tiene productos.',
        'rows' => $invoice->items->map(fn ($item) => [
            $item->description,
            (string) $item->quantity,
            '$'.number_format((float) $item->unit_price, 2),
            '$'.number_format((float) $item->discount_total, 2),
            '$'.number_format((float) $item->line_total, 2),
        ])->all(),
    ]" />

    <div class="flex justify-between mt-lg">
        <a href="{{ route('invoices.draft.items', $invoice) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">Volver a productos</a>
        <a href="{{ route('invoices.draft.validation', $invoice) }}" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover">Continuar a validación</a>
    </div>
</x-layouts.app>
