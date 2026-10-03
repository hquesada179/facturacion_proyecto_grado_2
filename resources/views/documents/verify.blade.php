<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación de documento de prueba | {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface antialiased font-body-md text-body-md">
    <main class="min-h-screen flex items-center justify-center p-margin-mobile md:p-margin-desktop">
        <section class="w-full max-w-2xl bg-surface-container-lowest border border-outline-variant/50 rounded-lg p-lg shadow-soft">
            <div class="flex items-center gap-xs mb-xs">
                <img src="{{ asset('branding/fiscora-icon.png') }}" alt="Fiscora" class="h-4 w-4 object-contain">
                <p class="font-label-sm text-label-sm text-primary uppercase">Fiscora</p>
            </div>
            <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface mb-sm">
                Verificación de documento de prueba
            </h1>

            <div class="mb-lg bg-[#fdf6b2] border border-[#723b13]/20 text-[#723b13] rounded-lg p-md font-body-sm text-body-sm">
                Documento de prueba – sin validez tributaria. Validación DIAN simulada.
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-md font-body-sm text-body-sm">
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Estado</dt>
                    <dd class="mt-xs"><x-ui.badge :status="$invoice->status->label()" /></dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Número</dt>
                    <dd class="mt-xs font-mono">{{ $invoice->number }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Empresa emisora</dt>
                    <dd class="mt-xs">{{ $issuer['legal_name'] ?? $issuer['name'] ?? 'Empresa emisora' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Fecha</dt>
                    <dd class="mt-xs">{{ optional($invoice->issued_at)->format('Y-m-d H:i') }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">Valor total</dt>
                    <dd class="mt-xs font-mono">{{ $invoice->currency }} {{ \App\Support\ReportFormatter::money($invoice->total) }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-label-sm text-on-surface-variant">CUFE simulado</dt>
                    <dd class="mt-xs font-mono break-all">{{ $invoice->simulated_cufe }}</dd>
                </div>
            </dl>

            <p class="mt-lg pt-md border-t border-outline-variant/40 text-center font-label-sm text-label-sm text-on-surface-variant">
                Fiscora · Plataforma de Facturación
            </p>
        </section>
    </main>
</body>
</html>
