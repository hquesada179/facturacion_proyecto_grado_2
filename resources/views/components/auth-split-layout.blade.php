{{--
    Shared shell for every auth screen (login, recuperar/restablecer
    contraseña) — inspired by TechAuth's two-column composition (brand
    panel left, clean form right, both sitting inside one wide centered
    card on a soft background) but built entirely from Fiscora's own
    tokens/copy/icons. One shared component instead of tripling this
    markup across login.blade.php/password.blade.php/reset-password.blade.php
    — the exact "same shell reused between screens" pattern TechAuth itself
    uses between signin and forgot-password.

    Each page only ever supplies its own right-column content (heading,
    form, errors) via the default slot; the brand panel is identical and
    static on every screen.
--}}
<div class="min-h-screen bg-surface relative overflow-hidden flex items-center justify-center p-margin-mobile md:p-margin-desktop">
    <div class="absolute inset-0 bg-gradient-to-br from-primary/[0.05] via-transparent to-primary/[0.08]" aria-hidden="true"></div>

    <div class="relative z-10 w-full max-w-5xl bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden flex flex-col lg:flex-row">
        <section class="hidden lg:flex lg:w-[42%] bg-primary text-on-primary flex-col justify-between p-xl relative overflow-hidden shrink-0">
            <div class="absolute inset-x-0 top-0 h-[3px] bg-brand-gold"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-md mb-xl">
                    <img src="{{ asset('branding/fiscora-icon-transparent.png') }}" alt="Fiscora" class="h-11 w-11 object-contain shrink-0">
                    <div>
                        <p class="font-headline-md text-headline-md tracking-[0.12em]">FISCORA</p>
                        <p class="font-label-sm text-label-sm text-brand-gold tracking-[0.18em] uppercase">Plataforma de Facturación</p>
                    </div>
                </div>

                <h1 class="font-headline-lg text-headline-lg mb-md">Facturación clara, trazable y asistida.</h1>
                <p class="font-body-md text-body-md opacity-90 max-w-[24rem] mb-xl">Gestiona clientes, productos, facturas y notas crédito desde una experiencia simple y segura.</p>

                <div class="space-y-lg">
                    @foreach ([
                        ['receipt_long', 'Facturación simulada'],
                        ['history', 'Trazabilidad documental'],
                        ['auto_awesome', 'Asistente IA contextual'],
                        ['shield', 'Gestión segura por empresa'],
                    ] as [$icon, $text])
                        <div class="flex items-center gap-md">
                            <div class="bg-primary-container p-sm rounded-lg flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[20px]">{{ $icon }}</span>
                            </div>
                            <p class="font-title-lg text-title-lg">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative z-10 font-label-sm text-label-sm text-on-primary/60">Prototipo académico · Documentos de prueba sin validez tributaria</p>
        </section>

        <section class="flex-1 flex flex-col justify-center p-margin-mobile md:p-xl">
            <div class="lg:hidden flex items-center gap-sm mb-xl text-primary">
                <img src="{{ asset('branding/fiscora-icon.png') }}" alt="Fiscora" class="h-8 w-8 object-contain">
                <span class="font-headline-md text-headline-md">Fiscora</span>
            </div>

            <div class="w-full max-w-[26rem] mx-auto lg:mx-0">
                {{ $slot }}
            </div>
        </section>
    </div>
</div>
