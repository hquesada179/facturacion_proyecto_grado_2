<x-layouts.app title="Integraciones — WhatsApp" active="settings">
    <x-page-header eyebrow="Configuración" title="Integraciones" description="Conecta Fiscora con otros canales. Por ahora, WhatsApp — un prototipo académico usando temporalmente el número de atención de VYA." />

    <x-settings-tabs />

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-lg">{{ session('status') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert tone="error" class="mb-lg">{{ session('error') }}</x-ui.alert>
    @endif

    @unless ($bridgeEnabled)
        <x-ui.alert tone="neutral" icon="info" class="mb-lg">
            La integración de WhatsApp está temporalmente deshabilitada en este entorno. Puedes vincular tu número, pero los mensajes no se procesarán hasta que se active.
        </x-ui.alert>
    @endunless

    <section class="surface-card p-lg max-w-2xl">
        @if ($connection)
            {{-- ===================== VINCULADO ===================== --}}
            <div class="flex items-start gap-md mb-md">
                <span class="w-11 h-11 rounded-full bg-success-soft text-success-soft-foreground flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined">check_circle</span>
                </span>
                <div>
                    <h3 class="font-title-lg text-title-lg text-on-surface">WhatsApp conectado</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Ya puedes escribirle al asistente de Fiscora desde este número.</p>
                </div>
            </div>

            <div class="auth-premium-mini-card mb-lg" style="border:1.5px solid var(--color-outline-variant);border-radius:var(--radius-lg);padding:var(--spacing-md);background:var(--color-surface-container-lowest);">
                <div class="flex justify-between py-xs text-body-sm font-body-sm border-b border-dashed border-outline-variant">
                    <span class="text-on-surface-variant">Número</span>
                    <span class="font-mono font-semibold">{{ $connection->maskedWaId() }}</span>
                </div>
                <div class="flex justify-between py-xs text-body-sm font-body-sm border-b border-dashed border-outline-variant">
                    <span class="text-on-surface-variant">Vinculado desde</span>
                    <span class="font-semibold">{{ $connection->verified_at?->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between py-xs text-body-sm font-body-sm">
                    <span class="text-on-surface-variant">Empresa</span>
                    <span class="font-semibold">{{ $connection->company->name }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.integrations.whatsapp.unlink') }}" onsubmit="return confirm('¿Desvincular este WhatsApp de Fiscora? Dejará de poder usar el asistente desde este número hasta que lo vincules de nuevo.');">
                @csrf
                <button type="submit" class="px-md py-sm rounded-lg border border-outline-variant text-error-soft-foreground hover:bg-error-soft font-label-md text-label-md flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">link_off</span>
                    Desvincular WhatsApp
                </button>
            </form>
        @else
            {{-- ===================== NO VINCULADO ===================== --}}
            <div class="flex items-start gap-md mb-md">
                <span class="w-11 h-11 rounded-full bg-neutral-soft text-on-surface-variant flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined">chat</span>
                </span>
                <div>
                    <h3 class="font-title-lg text-title-lg text-on-surface">WhatsApp</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Conecta tu cuenta para consultar Fiscora y utilizar el asistente desde WhatsApp.</p>
                </div>
            </div>

            @if ($pendingCode)
                <div class="rounded-lg border border-outline-variant bg-surface-container-low p-lg mb-lg" data-whatsapp-code-card data-expires-at="{{ $pendingCode['expires_at'] }}">
                    <p class="font-body-sm text-body-sm text-on-surface mb-sm">Envía este mensaje al WhatsApp de Fiscora:</p>
                    <div class="flex items-center gap-sm mb-sm">
                        <code class="flex-1 font-mono text-title-lg text-title-lg text-primary bg-surface-container-lowest border border-outline-variant rounded-lg px-md py-sm" data-whatsapp-code-text>VINCULAR {{ $pendingCode['code'] }}</code>
                        <button type="button" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md" data-whatsapp-copy-code>
                            Copiar
                        </button>
                    </div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">
                        Código de un solo uso. Vence en <span data-whatsapp-code-countdown>10:00</span> minutos.
                    </p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Si vence o ya lo usaste, puedes generar uno nuevo con el botón de abajo.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('settings.integrations.whatsapp.link') }}">
                @csrf
                <button type="submit" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm">
                    <span class="material-symbols-outlined text-[18px]">link</span>
                    {{ $pendingCode ? 'Generar un código nuevo' : 'Vincular WhatsApp' }}
                </button>
            </form>
        @endif
    </section>

    @if (! $connection && $pendingCode)
        <script>
            (function () {
                var card = document.querySelector('[data-whatsapp-code-card]');
                if (!card) return;

                var copyBtn = card.querySelector('[data-whatsapp-copy-code]');
                var textEl = card.querySelector('[data-whatsapp-code-text]');
                copyBtn?.addEventListener('click', function () {
                    navigator.clipboard?.writeText(textEl.textContent.trim());
                    copyBtn.textContent = 'Copiado';
                    setTimeout(function () { copyBtn.textContent = 'Copiar'; }, 2000);
                });

                var expiresAt = new Date(card.dataset.expiresAt).getTime();
                var countdownEl = card.querySelector('[data-whatsapp-code-countdown]');
                function tick() {
                    var remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                    var mm = String(Math.floor(remaining / 60)).padStart(2, '0');
                    var ss = String(remaining % 60).padStart(2, '0');
                    if (countdownEl) countdownEl.textContent = mm + ':' + ss;
                    if (remaining > 0) setTimeout(tick, 1000);
                }
                tick();
            })();
        </script>
    @endif
</x-layouts.app>
