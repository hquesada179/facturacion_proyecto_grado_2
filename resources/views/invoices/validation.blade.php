<x-layouts.app title="Nueva factura" active="invoices" screen="invoice.validation">
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Validación real de datos antes de finalizar el documento." />

    <x-invoice-stepper :current="4" />

    @php
        $toneClasses = fn (string $severity) => match ($severity) {
            'bloqueo' => 'bg-error-soft/60 text-error-soft-foreground border-error-soft-foreground/20',
            'advertencia' => 'bg-warning-soft/60 text-warning-soft-foreground border-warning-soft-foreground/20',
            default => 'bg-success-soft/60 text-success-soft-foreground border-success-soft-foreground/20',
        };
        $toneIcon = fn (string $severity) => match ($severity) {
            'bloqueo' => 'error',
            'advertencia' => 'warning',
            default => 'check_circle',
        };
        $results = $validation->all();
        $summaryTone = $validation->hasBlocking() ? 'bloqueo' : ($validation->warningCount() > 0 ? 'advertencia' : 'correcto');
    @endphp

    <div id="validation-summary" class="mb-lg rounded-xl p-md border flex items-start gap-sm font-body-sm text-body-sm {{ $toneClasses($summaryTone) }}">
        <span class="material-symbols-outlined text-[18px] shrink-0">{{ $toneIcon($summaryTone) }}</span>
        <span>
            @if ($validation->hasBlocking())
                No se puede continuar: {{ $validation->blockingCount() }} bloqueo(s) encontrados.
            @elseif ($validation->warningCount() > 0)
                Sin bloqueos, pero hay {{ $validation->warningCount() }} advertencia(s) para revisar.
            @else
                Validación exitosa: no se encontraron problemas.
            @endif
        </span>
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
                <div class="rounded-xl p-md border flex items-start gap-sm font-body-sm text-body-sm {{ $toneClasses($result->severidad->value) }}">
                    <span class="material-symbols-outlined text-[18px] shrink-0">{{ $toneIcon($result->severidad->value) }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-label-md text-label-md font-semibold mb-xs">{{ $result->regla }} <span class="font-mono text-label-sm text-label-sm font-normal opacity-70">({{ $result->codigo }})</span></p>
                        <p>{{ $result->mensaje }}</p>
                        <p class="mt-xs opacity-80">Campo: {{ $result->campo }} — {{ $result->sugerencia }}</p>
                    </div>
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
                if (severity === 'bloqueo') return 'bg-error-soft/60 text-error-soft-foreground border-error-soft-foreground/20';
                if (severity === 'advertencia') return 'bg-warning-soft/60 text-warning-soft-foreground border-warning-soft-foreground/20';
                return 'bg-success-soft/60 text-success-soft-foreground border-success-soft-foreground/20';
            }

            function toneIcon(severity) {
                if (severity === 'bloqueo') return 'error';
                if (severity === 'advertencia') return 'warning';
                return 'check_circle';
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
                summaryBanner.className = 'mb-lg rounded-xl p-md border flex items-start gap-sm font-body-sm text-body-sm ' + toneClasses(bannerTone);
                summaryBanner.innerHTML =
                    '<span class="material-symbols-outlined text-[18px] shrink-0">' + toneIcon(bannerTone) + '</span>' +
                    '<span></span>';
                summaryBanner.lastElementChild.textContent = bannerText;

                if (results.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'font-body-sm text-body-sm text-on-surface-variant';
                    empty.textContent = 'No hay resultados de validación.';
                    resultsContainer.appendChild(empty);
                }

                results.forEach(function (result) {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl p-md border flex items-start gap-sm font-body-sm text-body-sm ' + toneClasses(result.severidad);
                    row.innerHTML =
                        '<span class="material-symbols-outlined text-[18px] shrink-0">' + toneIcon(result.severidad) + '</span>' +
                        '<div class="flex-1 min-w-0">' +
                        '<p class="font-label-md text-label-md font-semibold mb-xs">' + result.regla + ' <span class="font-mono text-label-sm text-label-sm font-normal opacity-70">(' + result.codigo + ')</span></p>' +
                        '<p>' + result.mensaje + '</p>' +
                        '<p class="mt-xs opacity-80">Campo: ' + result.campo + ' — ' + result.sugerencia + '</p>' +
                        '</div>';
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
