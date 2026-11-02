@props(['compact' => false])

{{--
    Decorative composition built from Fiscora's own visual language
    (window chrome, KPI tiles, document rows, status pills) — matches
    the approved artifact's hero mockup, not a screenshot of anything.
    Every label is explicit demo data (FV-000182, "Comercial Andina
    SAS"...), never presented as a real customer or real production use.
    `data-landing-tilt-stage`/`-target` are what landing.js's pointer
    tilt hooks into (desktop + hover-capable only — see the JS guard);
    without JS, `.landing-tilt`'s CSS-set resting transform still looks
    intentional, not broken.
--}}
<div class="relative" data-landing-tilt-stage aria-hidden="true">
    <div class="landing-tilt rounded-[10px] bg-white shadow-[0_40px_90px_-25px_rgba(0,0,0,0.45)] overflow-hidden w-full {{ $compact ? 'max-w-[26rem]' : 'max-w-[30rem]' }} mx-auto" data-landing-tilt-target>
        <div class="flex items-center gap-sm px-md py-sm bg-[#eef1f8] border-b border-[#e1e5ef]">
            <span class="w-[9px] h-[9px] rounded-full bg-[#cfd5e4]"></span>
            <span class="w-[9px] h-[9px] rounded-full bg-[#cfd5e4]"></span>
            <span class="w-[9px] h-[9px] rounded-full bg-[#cfd5e4]"></span>
            <span class="ml-xs font-label-sm text-label-sm text-[#7c8aa8]">Panel de facturación — Fiscora</span>
        </div>

        <div class="p-lg">
            <div class="grid grid-cols-2 gap-sm mb-md">
                <div class="bg-[#f3f5fb] rounded-lg p-md">
                    <p class="font-label-sm text-label-sm text-[#6b7694] mb-xs">Facturas del mes</p>
                    <p class="font-headline-md text-headline-md text-primary font-mono tabular-nums">24</p>
                </div>
                <div class="bg-[#f3f5fb] rounded-lg p-md">
                    <p class="font-label-sm text-label-sm text-[#6b7694] mb-xs">Notas crédito</p>
                    <p class="font-headline-md text-headline-md text-primary font-mono tabular-nums">03</p>
                </div>
            </div>

            <div class="space-y-xs">
                @foreach ([
                    ['id' => 'FV-000182', 'name' => 'Comercial Andina SAS', 'status' => 'Emitida', 'tone' => 'success'],
                    ['id' => 'FV-000181', 'name' => 'Distribuidora RS', 'status' => 'Borrador', 'tone' => 'warning'],
                    ['id' => 'NC-000042', 'name' => 'Comercial Andina SAS', 'status' => 'Emitida', 'tone' => 'success'],
                ] as $row)
                    <div class="flex items-center justify-between py-xs text-body-sm font-body-sm">
                        <span class="font-mono tabular-nums text-[#56607c]">{{ $row['id'] }}</span>
                        <span class="text-on-surface truncate px-sm">{{ $row['name'] }}</span>
                        <span class="shrink-0 inline-flex items-center px-sm py-xs rounded-full font-label-sm text-label-sm {{ $row['tone'] === 'success' ? 'bg-success-soft text-success-soft-foreground' : 'bg-warning-soft text-warning-soft-foreground' }}">
                            {{ $row['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <p class="font-label-sm text-label-sm text-outline mt-md">Datos de demostración visual, no corresponden a una empresa real.</p>
        </div>
    </div>

    @unless ($compact)
        <div class="landing-float hidden sm:block absolute -top-6 -left-8 w-56 rounded-2xl bg-white shadow-2xl p-md border-t-2 border-brand-gold">
            <div class="flex items-center gap-sm mb-xs">
                <span class="w-6 h-6 rounded-lg ai-gradient flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-white text-[14px]">auto_awesome</span>
                </span>
                <span class="font-label-sm text-label-sm text-on-surface-variant font-semibold">Asistente IA</span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface">"La factura FV-000181 está en borrador: le falta un producto para validarla."</p>
        </div>

        <div class="landing-float hidden sm:flex absolute -bottom-6 -right-8 items-center gap-sm bg-white shadow-2xl rounded-2xl p-md" style="animation-delay:1.4s">
            <span class="w-8 h-8 rounded-full bg-success-soft text-success-soft-foreground flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px]">check</span>
            </span>
            <div>
                <p class="font-label-md text-label-md text-on-surface font-mono tabular-nums">FV-000182</p>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Validada y emitida</p>
            </div>
        </div>
    @endunless
</div>
