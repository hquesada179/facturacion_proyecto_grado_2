<x-layouts.app
    title="Nueva factura"
    active="invoices"
    screen="invoice.validation"
    resource-type="invoice"
    :resource-id="$invoice->id"
>
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Validación simulada sobre el borrador real antes de emitir." />

    <x-invoice-stepper :current="4" :invoice="$invoice" />

    <div class="mb-lg bg-[#fdf6b2] border border-[#723b13]/20 text-[#723b13] rounded-lg p-md font-body-sm text-body-sm flex items-center gap-sm">
        <span class="material-symbols-outlined text-[18px]">science</span>
        Validación simulada — Documento de prueba, sin validez tributaria.
    </div>

    @php
        $toneClasses = fn (string $severity) => match ($severity) {
            'bloqueo' => 'bg-[#fde8e8] text-[#9b1c1c] border-[#9b1c1c]/20',
            'advertencia' => 'bg-[#fdf6b2] text-[#723b13] border-[#723b13]/20',
            default => 'bg-[#def7ec] text-[#03543f] border-[#03543f]/20',
        };
        $summaryTone = $validation->hasBlocking() ? 'bloqueo' : ($validation->warningCount() > 0 ? 'advertencia' : 'correcto');
    @endphp

    <div class="mb-lg rounded-lg p-md font-body-sm text-body-sm border {{ $toneClasses($summaryTone) }}">
        @if ($validation->hasBlocking())
            No se puede continuar: {{ $validation->blockingCount() }} bloqueo(s) encontrados.
        @elseif ($validation->warningCount() > 0)
            Sin bloqueos, pero hay {{ $validation->warningCount() }} advertencia(s) para revisar.
        @else
            Validación exitosa: no se encontraron problemas.
        @endif
    </div>

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
                <div class="rounded-lg p-md border font-body-sm text-body-sm {{ $toneClasses($result->severidad->value) }}">
                    <p class="font-label-md text-label-md mb-xs">{{ $result->regla }} <span class="font-mono text-xs opacity-70">({{ $result->codigo }})</span></p>
                    <p>{{ $result->mensaje }}</p>
                    <p class="mt-xs opacity-80">Campo: {{ $result->campo }} — {{ $result->sugerencia }}</p>
                </div>
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
