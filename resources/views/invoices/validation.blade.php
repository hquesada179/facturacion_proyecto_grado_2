<x-layouts.app title="Nueva factura" active="invoices" screen="invoice.validation">
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Validación real de datos antes de finalizar el documento." />

    <x-invoice-stepper :current="4" />

    @php
        $toneClasses = fn (string $severity) => match ($severity) {
            'bloqueo' => 'bg-[#fde8e8] text-[#9b1c1c] border-[#9b1c1c]/20',
            'advertencia' => 'bg-[#fdf6b2] text-[#723b13] border-[#723b13]/20',
            default => 'bg-[#def7ec] text-[#03543f] border-[#03543f]/20',
        };
        $results = $validation->all();
        $summaryTone = $validation->hasBlocking() ? 'bloqueo' : ($validation->warningCount() > 0 ? 'advertencia' : 'correcto');
    @endphp

    <div id="validation-summary" class="mb-lg rounded-lg p-md font-body-sm text-body-sm border {{ $toneClasses($summaryTone) }}">
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
            <p id="calc-subtotal" class="font-title-lg text-title-lg text-on-surface font-mono">${{ $calculation->subtotal }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Impuestos</p>
            <p id="calc-taxes" class="font-title-lg text-title-lg text-on-surface font-mono">${{ $calculation->totalTaxes }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Total a pagar</p>
            <p id="calc-total" class="font-title-lg text-title-lg text-on-surface font-mono">${{ $calculation->total }}</p>
        </div>
    </div>

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
        <div class="flex items-center justify-between mb-md">
            <h3 class="font-title-lg text-title-lg text-on-surface">Resultados de validación</h3>
            <button id="revalidate-button" type="button" class="px-md py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                Revalidar
            </button>
        </div>

        <div id="validation-results" class="space-y-sm">
            @forelse ($results as $result)
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

    <script>
        (function () {
            const payload = @json($draftPayload);
            const button = document.getElementById('revalidate-button');
            const resultsContainer = document.getElementById('validation-results');
            const summaryBanner = document.getElementById('validation-summary');
            const subtotalEl = document.getElementById('calc-subtotal');
            const taxesEl = document.getElementById('calc-taxes');
            const totalEl = document.getElementById('calc-total');

            function toneClasses(severity) {
                if (severity === 'bloqueo') return 'bg-[#fde8e8] text-[#9b1c1c] border-[#9b1c1c]/20';
                if (severity === 'advertencia') return 'bg-[#fdf6b2] text-[#723b13] border-[#723b13]/20';
                return 'bg-[#def7ec] text-[#03543f] border-[#03543f]/20';
            }

            function renderResults(data) {
                const results = data.validation.results;
                resultsContainer.innerHTML = '';

                let bannerTone;
                let bannerText;
                if (!data.validation.ok) {
                    bannerTone = 'bloqueo';
                    bannerText = 'No se puede continuar: ' + data.validation.blocking_count + ' bloqueo(s) encontrados.';
                } else if (data.validation.warning_count > 0) {
                    bannerTone = 'advertencia';
                    bannerText = 'Sin bloqueos, pero hay ' + data.validation.warning_count + ' advertencia(s) para revisar.';
                } else {
                    bannerTone = 'correcto';
                    bannerText = 'Validación exitosa: no se encontraron problemas.';
                }
                summaryBanner.className = 'mb-lg rounded-lg p-md font-body-sm text-body-sm border ' + toneClasses(bannerTone);
                summaryBanner.textContent = bannerText;

                if (results.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'font-body-sm text-body-sm text-on-surface-variant';
                    empty.textContent = 'No hay resultados de validación.';
                    resultsContainer.appendChild(empty);
                }

                results.forEach(function (result) {
                    const row = document.createElement('div');
                    row.className = 'rounded-lg p-md border font-body-sm text-body-sm ' + toneClasses(result.severidad);
                    row.innerHTML =
                        '<p class="font-label-md text-label-md mb-xs">' + result.regla + ' <span class="font-mono text-xs opacity-70">(' + result.codigo + ')</span></p>' +
                        '<p>' + result.mensaje + '</p>' +
                        '<p class="mt-xs opacity-80">Campo: ' + result.campo + ' — ' + result.sugerencia + '</p>';
                    resultsContainer.appendChild(row);
                });

                subtotalEl.textContent = '$' + data.calculation.subtotal;
                taxesEl.textContent = '$' + data.calculation.total_taxes;
                totalEl.textContent = '$' + data.calculation.total;
            }

            button.addEventListener('click', function () {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                button.disabled = true;
                fetch('{{ route('invoices.validate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                })
                    .then(function (response) { return response.json(); })
                    .then(renderResults)
                    .finally(function () { button.disabled = false; });
            });
        })();
    </script>
</x-layouts.app>
