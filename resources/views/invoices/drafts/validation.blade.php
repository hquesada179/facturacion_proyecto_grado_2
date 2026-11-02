<x-layouts.app
    title="Nueva factura"
    active="invoices"
    screen="invoice.validation"
    resource-type="invoice"
    :resource-id="$invoice->id"
>
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Validación simulada sobre el borrador real antes de emitir." />

    <x-invoice-stepper :current="4" :invoice="$invoice" />

    <x-ui.alert tone="neutral" icon="science" class="mb-lg">
        Validación simulada — Documento de prueba, sin validez tributaria.
    </x-ui.alert>

    @php
        $summaryTone = $validation->hasBlocking() ? 'error' : ($validation->warningCount() > 0 ? 'warning' : 'success');
        $summaryTitle = $validation->hasBlocking()
            ? 'No se puede continuar'
            : ($validation->warningCount() > 0 ? 'Hay advertencias para revisar' : 'Validación exitosa');

        $stepFor = function (string $campo) use ($invoice): ?array {
            if ($campo === 'customer_id' || str_starts_with($campo, 'customer.')) {
                return ['label' => 'Ir a cliente', 'href' => route('invoices.draft.customer', $invoice)];
            }
            if (str_starts_with($campo, 'items') || in_array($campo, ['subtotal', 'total_taxes'], true)) {
                return ['label' => 'Ir a productos', 'href' => route('invoices.draft.items', $invoice)];
            }
            if (in_array($campo, ['payment_type', 'payment_method', 'due_date', 'issue_date'], true)) {
                return ['label' => 'Corregir datos de pago', 'href' => route('invoices.draft.summary', $invoice)];
            }

            return null;
        };
    @endphp

    <x-ui.alert :tone="$summaryTone" :title="$summaryTitle" class="mb-lg">
        @if ($validation->hasBlocking())
            {{ $validation->blockingCount() }} bloqueo(s) encontrados. Corrígelos para poder continuar.
        @elseif ($validation->warningCount() > 0)
            Sin bloqueos, pero hay {{ $validation->warningCount() }} advertencia(s) para revisar.
        @else
            No se encontraron problemas.
        @endif
    </x-ui.alert>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Subtotal</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ $calculation->subtotal }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Impuestos</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ $calculation->totalTaxes }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total a pagar</p>
            <p class="font-title-lg text-title-lg text-on-surface font-mono">${{ $calculation->total }}</p>
        </div>
    </div>

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Resultados de validación</h3>
        <div class="space-y-sm">
            @forelse ($validation->all() as $result)
                @php($action = $stepFor($result->campo))
                <x-ui.validation-result
                    :severity="$result->severidad->value"
                    :rule="$result->regla"
                    :code="$result->codigo"
                    :message="$result->mensaje"
                    :field="$result->campo"
                    :suggestion="$result->sugerencia"
                    :action-href="$action['href'] ?? null"
                    :action-label="$action['label'] ?? null"
                />
            @empty
                <p class="font-body-sm text-body-sm text-on-surface-variant">No hay resultados de validación.</p>
            @endforelse
        </div>
    </section>

    @if ($invoice->dian_simulation_result)
        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Último intento de emisión (simulado)</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $invoice->dian_simulation_message }}</p>
        </section>
    @endif

    <div class="flex flex-col sm:flex-row justify-between gap-sm">
        <div class="flex gap-sm">
            <a href="{{ route('invoices.draft.summary', $invoice) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">Volver a resumen</a>
            @if (\App\Services\Invoices\InvoiceStateMachine::isEditable($invoice->status))
                <form method="POST" action="{{ route('invoices.draft.discard', $invoice) }}" onsubmit="return confirm('¿Descartar este borrador de factura?');">
                    @csrf
                    <button type="submit" class="px-md py-sm rounded-lg border border-outline-variant text-[#9b1c1c] hover:bg-[#fde8e8] font-label-md text-label-md">Descartar borrador</button>
                </form>
            @endif
        </div>

        @if ($validation->hasBlocking())
            <span class="px-lg py-sm rounded-lg bg-surface-variant text-on-surface-variant font-label-md text-label-md text-center">Corrige los bloqueos para continuar</span>
        @elseif ($invoice->status->value === 'locally_validated')
            <form method="POST" action="{{ route('invoices.draft.issue', $invoice) }}" onsubmit="return confirm('¿Emitir esta factura (simulado)? No podrás editarla después.');">
                @csrf
                <button type="submit" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">send</span>
                    Emitir factura (simulado)
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('invoices.draft.validate', $invoice) }}" @if($validation->warningCount() > 0) onsubmit="return confirm('Hay advertencias pendientes. ¿Deseas continuar de todas formas?');" @endif>
                @csrf
                <button type="submit" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    Confirmar y continuar
                </button>
            </form>
        @endif
    </div>
</x-layouts.app>
