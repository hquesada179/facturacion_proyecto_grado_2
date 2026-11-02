<x-layouts.auth title="Facturación clara, trazable y asistida por IA">
    {{--
        Premium landing — implements the Claude-Design-style mockup
        approved for Fiscora's public `/`. Everything below lives inside
        `.landing` (see app.css) so its deliberately fixed navy/paper
        editorial world never bleeds into the authenticated app, and
        never responds to the app's light/dark toggle — same reasoning
        as the login screen's own brand panel: a fixed brand surface,
        not a themed one.
    --}}
    <div class="landing">
        <x-landing.navbar />

        {{-- HERO --}}
        <header id="inicio" class="relative overflow-hidden text-white" style="background:
            radial-gradient(ellipse 60% 50% at 18% 0%, color-mix(in srgb, var(--color-primary) 55%, transparent), transparent 60%),
            linear-gradient(180deg, var(--landing-primary-deep), #081433 60%, #0b1a3e);">
            <div class="landing-blob w-[360px] h-[360px] bg-brand-gold -top-28 right-[6%]" style="animation: landing-drift-a 22s ease-in-out infinite"></div>
            <div class="landing-blob w-[420px] h-[420px] bg-[#2546a8] -bottom-44 left-[2%]" style="animation: landing-drift-b 26s ease-in-out infinite"></div>

            <div class="relative z-10 max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop pt-[7.5rem] md:pt-[9rem] pb-[5rem] md:pb-[7.5rem] grid grid-cols-1 lg:grid-cols-2 gap-xl items-center">
                <div data-reveal>
                    <p class="font-label-sm text-label-sm text-[var(--landing-gold-soft)] uppercase tracking-[0.14em] mb-md">Prototipo académico de facturación electrónica</p>
                    <h1 class="font-display-lg text-display-lg mb-md" style="line-height:1.06">Facturación clara, trazable y <em class="text-[var(--landing-gold-soft)]">asistida por IA</em></h1>
                    <p class="font-body-lg text-body-lg text-white/80 max-w-[32rem] mb-lg">Gestiona clientes, productos, facturas y notas crédito desde una plataforma pensada para que el control administrativo de tu empresa deje de ser un problema.</p>

                    <div class="flex flex-wrap items-center gap-md mb-lg">
                        <a href="{{ route('register') }}" class="landing-btn inline-flex items-center gap-xs px-lg py-md rounded-full bg-brand-gold text-primary font-label-md text-label-md font-semibold">
                            Crear cuenta
                            <span class="landing-btn-arrow">→</span>
                        </a>
                        <a href="#como-funciona" class="landing-btn inline-flex items-center px-lg py-md rounded-full border border-white/35 text-white font-label-md text-label-md hover:border-white">
                            Ver cómo funciona
                        </a>
                    </div>

                    <div class="flex flex-wrap gap-lg font-label-sm text-label-sm text-white/60">
                        <span class="flex items-center gap-xs"><span class="w-1.5 h-1.5 rounded-full bg-brand-gold"></span>Validación local antes de emitir</span>
                        <span class="flex items-center gap-xs"><span class="w-1.5 h-1.5 rounded-full bg-brand-gold"></span>Trazabilidad append-only</span>
                        <span class="flex items-center gap-xs"><span class="w-1.5 h-1.5 rounded-full bg-brand-gold"></span>Aislamiento por empresa</span>
                    </div>
                </div>

                <div data-reveal>
                    <x-landing.dashboard-mockup />
                </div>
            </div>
        </header>

        {{-- FUNCIONALIDADES --}}
        <section id="funcionalidades" class="py-xl md:py-[5rem] bg-white">
            <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop">
                <x-landing.section-heading
                    eyebrow="Funcionalidades"
                    title="Todo lo que necesitas para facturar con control"
                    description="Un flujo simple de cliente a documento, con trazabilidad y validación en cada paso."
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-lg" data-reveal-group>
                    <x-landing.feature-card title="Facturación simulada">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M6 2h9l4 4v16H6V2Z" stroke="currentColor" stroke-width="1.6"/><path d="M9 11h6M9 15h6" stroke="currentColor" stroke-width="1.6"/></svg>
                        </x-slot:icon>
                        Crea facturas y notas crédito con validación local y emisión simulada, sin conexión real a la DIAN.
                    </x-landing.feature-card>

                    <x-landing.feature-card title="Trazabilidad documental">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </x-slot:icon>
                        Cada factura conserva un registro append-only de sus eventos: quién, cuándo y qué cambió.
                    </x-landing.feature-card>

                    <x-landing.feature-card title="Asistente IA contextual">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 3l2.4 5.3L20 9l-4 4 1 5.6L12 16l-5 2.6 1-5.6-4-4 5.6-.7L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                        </x-slot:icon>
                        Responde preguntas sobre tus documentos y puede preparar propuestas que tú confirmas antes de ejecutar.
                    </x-landing.feature-card>

                    <x-landing.feature-card title="Gestión segura por empresa">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2l8 3.5v6C20 16.5 16.4 20.3 12 22 7.6 20.3 4 16.5 4 11.5v-6L12 2Z" stroke="currentColor" stroke-width="1.6"/></svg>
                        </x-slot:icon>
                        Cada empresa tiene su propio espacio aislado de datos, con roles y permisos diferenciados.
                    </x-landing.feature-card>
                </div>
            </div>
        </section>

        {{-- CÓMO FUNCIONA --}}
        <section id="como-funciona" class="py-xl md:py-[5rem]" style="background:linear-gradient(180deg,#071535,#0a1c42)">
            <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop">
                <x-landing.section-heading
                    tone="dark"
                    eyebrow="Cómo funciona"
                    title="Un flujo de cinco pasos, de principio a fin"
                    description="El mismo recorrido guiado que usa el asistente al preparar una propuesta de factura."
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-lg" data-reveal-group>
                    @foreach ([
                        ['num' => '01', 'label' => 'Cliente', 'icon' => '<circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6" stroke="currentColor" stroke-width="1.5"/>'],
                        ['num' => '02', 'label' => 'Productos / servicios', 'icon' => '<path d="M4 8l8-4 8 4-8 4-8-4Z" stroke="currentColor" stroke-width="1.5"/><path d="M4 8v8l8 4 8-4V8" stroke="currentColor" stroke-width="1.5"/>'],
                        ['num' => '03', 'label' => 'Resumen', 'icon' => '<path d="M5 4h14v16H5V4Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.5"/>'],
                        ['num' => '04', 'label' => 'Validación', 'icon' => '<path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'],
                        ['num' => '05', 'label' => 'Documento', 'icon' => '<path d="M7 3h7l4 4v14H7V3Z" stroke="currentColor" stroke-width="1.5"/>'],
                    ] as $index => $step)
                        <div data-reveal style="--i:{{ $index }}" class="relative text-center">
                            <p class="font-mono text-label-sm tabular-nums text-[var(--landing-gold-soft)]/90 mb-sm tracking-[0.04em]">{{ $step['num'] }}</p>
                            <div class="w-[46px] h-[46px] rounded-full bg-white/10 border border-white/22 text-[var(--landing-gold-soft)] flex items-center justify-center mx-auto mb-sm">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">{!! $step['icon'] !!}</svg>
                            </div>
                            <p class="font-label-md text-label-md text-white">{{ $step['label'] }}</p>
                            @if (! $loop->last)
                                <span class="hidden lg:block absolute top-[31px] left-full w-7 h-px bg-gradient-to-r from-white/30 to-transparent"></span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- PRODUCTO --}}
        <section id="producto" class="py-xl md:py-[5rem] bg-[var(--landing-paper-dim)]">
            <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop">
                <x-landing.section-heading
                    eyebrow="Producto"
                    title="Un módulo para cada parte del ciclo de facturación"
                    description="Facturas, notas crédito, reportes, trazabilidad y el asistente IA, integrados en una sola plataforma."
                />

                <div class="flex flex-col gap-[6rem] mb-xl">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-xl items-center">
                        <div data-reveal>
                            <p class="font-label-sm text-label-sm text-primary uppercase tracking-[0.1em] mb-sm">Facturas y notas crédito</p>
                            <h3 class="font-headline-md text-headline-md text-on-surface mb-sm">De cliente a documento sin perder el control</h3>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-md max-w-[42ch]">Un asistente de creación guiado por pasos genera documentos con numeración consecutiva, cálculo de impuestos y un CUFE simulado — listos para su PDF y verificación por código QR.</p>
                            <ul class="space-y-sm">
                                <li class="flex items-start gap-sm font-body-sm text-body-sm text-on-surface-variant">
                                    <svg class="text-success-soft-foreground shrink-0 mt-[2px]" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Validación local antes de emitir
                                </li>
                                <li class="flex items-start gap-sm font-body-sm text-body-sm text-on-surface-variant">
                                    <svg class="text-success-soft-foreground shrink-0 mt-[2px]" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Notas crédito por anulación total o parcial
                                </li>
                                <li class="flex items-start gap-sm font-body-sm text-body-sm text-on-surface-variant">
                                    <svg class="text-success-soft-foreground shrink-0 mt-[2px]" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    PDF y verificación pública por código QR
                                </li>
                            </ul>
                        </div>
                        <div data-reveal>
                            <x-landing.dashboard-mockup compact />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-xl items-center">
                        <div data-reveal class="lg:order-2">
                            <p class="font-label-sm text-label-sm text-primary uppercase tracking-[0.1em] mb-sm">Asistente IA contextual</p>
                            <h3 class="font-headline-md text-headline-md text-on-surface mb-sm">Entiende tus documentos, nunca actúa solo</h3>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-md max-w-[42ch]">Lee el contexto de la pantalla donde estás y responde con datos reales de tu empresa. Puede preparar propuestas de acción que siempre requieren tu confirmación explícita.</p>
                            <ul class="space-y-sm">
                                <li class="flex items-start gap-sm font-body-sm text-body-sm text-on-surface-variant">
                                    <svg class="text-success-soft-foreground shrink-0 mt-[2px]" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Responde preguntas sobre facturas, clientes y trazabilidad
                                </li>
                                <li class="flex items-start gap-sm font-body-sm text-body-sm text-on-surface-variant">
                                    <svg class="text-success-soft-foreground shrink-0 mt-[2px]" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Explica errores de validación en lenguaje claro
                                </li>
                                <li class="flex items-start gap-sm font-body-sm text-body-sm text-on-surface-variant">
                                    <svg class="text-success-soft-foreground shrink-0 mt-[2px]" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Nunca confirma ni ejecuta un cambio por sí mismo
                                </li>
                            </ul>
                        </div>
                        <div data-reveal class="lg:order-1">
                            <div class="rounded-[10px] bg-white shadow-[0_30px_60px_-24px_rgba(10,16,32,0.28)] border border-[color-mix(in_srgb,var(--color-on-surface)_6%,transparent)] overflow-hidden max-w-[28rem] mx-auto">
                                <div class="flex items-center gap-sm px-md py-sm bg-[#eef1f8] border-b border-[#e1e5ef]">
                                    <span class="w-[9px] h-[9px] rounded-full bg-[#cfd5e4]"></span>
                                    <span class="w-[9px] h-[9px] rounded-full bg-[#cfd5e4]"></span>
                                    <span class="w-[9px] h-[9px] rounded-full bg-[#cfd5e4]"></span>
                                    <span class="ml-xs font-label-sm text-label-sm text-[#7c8aa8]">Asistente IA</span>
                                </div>
                                <div class="p-lg flex flex-col gap-sm">
                                    <div class="ml-auto max-w-[82%] bg-primary text-on-primary rounded-lg rounded-br-[2px] px-md py-sm font-body-sm text-body-sm">¿Qué pasó con la factura FV-000181?</div>
                                    <div class="max-w-[82%] bg-[#f0f2f8] text-[#2d3550] rounded-lg rounded-bl-[2px] px-md py-sm font-body-sm text-body-sm">Está en borrador: le falta al menos un producto para poder validarla.</div>
                                    <p class="font-label-sm text-label-sm text-outline pt-sm mt-xs border-t border-dashed border-outline-variant">Toda acción crítica requiere confirmación humana antes de ejecutarse.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-lg" data-reveal-group>
                    <x-landing.feature-card title="Reportes">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 19V9M11 19V5M18 19v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </x-slot:icon>
                        Indicadores de facturación, IVA generado y productividad por usuario, filtrables por periodo.
                    </x-landing.feature-card>
                    <x-landing.feature-card title="Trazabilidad">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/><path d="M20 20l-4.3-4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </x-slot:icon>
                        Línea de tiempo append-only de cada documento: emisión, rechazos, anulaciones y acciones del asistente.
                    </x-landing.feature-card>
                    <x-landing.feature-card title="Asistente IA">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 3l2.4 5.3L20 9l-4 4 1 5.6L12 16l-5 2.6 1-5.6-4-4 5.6-.7L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                        </x-slot:icon>
                        Consulta tus documentos en lenguaje natural y prepara propuestas que confirmas tú mismo.
                    </x-landing.feature-card>
                </div>
            </div>
        </section>

        {{-- SEGURIDAD --}}
        <section id="seguridad" class="py-xl md:py-[5rem]" style="background:linear-gradient(180deg,#071535,#0a1c42)">
            <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop">
                <x-landing.section-heading
                    tone="dark"
                    eyebrow="Seguridad y aislamiento"
                    title="Cada empresa, su propio espacio"
                    description="El acceso y los datos se controlan por empresa y por rol — ninguna acción crítica ocurre sin que una persona la confirme."
                />

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-lg" data-reveal-group>
                    <x-landing.feature-card tone="dark" title="Aislamiento por empresa">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2l8 3.5v6C20 16.5 16.4 20.3 12 22 7.6 20.3 4 16.5 4 11.5v-6L12 2Z" stroke="currentColor" stroke-width="1.6"/></svg>
                        </x-slot:icon>
                        Cada usuario solo puede ver y modificar la información de su propia empresa, sin excepción.
                    </x-landing.feature-card>
                    <x-landing.feature-card tone="dark" title="Roles y permisos">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6"/></svg>
                        </x-slot:icon>
                        Administrador, Facturador, Contador y Auditor tienen accesos distintos según su responsabilidad.
                    </x-landing.feature-card>
                    <x-landing.feature-card tone="dark" title="Confirmación humana">
                        <x-slot:icon>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 12l5 5L20 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </x-slot:icon>
                        Crear, emitir o anular un documento siempre requiere una acción explícita de la persona.
                    </x-landing.feature-card>
                </div>
            </div>
        </section>

        {{-- CTA FINAL --}}
        <section class="py-xl md:py-[4rem] text-center text-white" style="background:
            radial-gradient(ellipse 70% 60% at 50% 0%, color-mix(in srgb, var(--color-brand-gold) 20%, transparent), transparent 65%),
            linear-gradient(180deg,#071535,#030916);">
            <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop" data-reveal>
                <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg mb-sm">¿Listo para explorar Fiscora?</h2>
                <p class="font-body-md text-body-md text-white/75 max-w-[34rem] mx-auto mb-lg">Crea tu cuenta y recorre el flujo completo de facturación simulada, trazabilidad y asistencia IA.</p>
                <a href="{{ route('register') }}" class="landing-btn inline-flex items-center gap-xs px-lg py-md rounded-full bg-brand-gold text-primary font-label-md text-label-md font-semibold">
                    Crear cuenta
                    <span class="landing-btn-arrow">→</span>
                </a>
            </div>
        </section>

        {{-- FAQ --}}
        <section class="py-xl md:py-[5rem] bg-white">
            <div class="max-w-3xl mx-auto px-margin-mobile md:px-margin-desktop">
                <x-landing.section-heading
                    eyebrow="Preguntas frecuentes"
                    title="Lo que debes saber antes de explorar Fiscora"
                />

                <div>
                    <x-landing.faq-item question="¿Las facturas de Fiscora tienen validez tributaria real?">
                        No. Fiscora es un prototipo académico: la validación y la emisión ante la DIAN son simuladas, y los documentos generados no tienen validez tributaria.
                    </x-landing.faq-item>
                    <x-landing.faq-item question="¿Qué puede hacer realmente el Asistente IA?">
                        Responde preguntas sobre tus documentos y puede preparar propuestas de acción (como crear un cliente o un borrador de factura). Nunca ejecuta un cambio sin tu confirmación explícita.
                    </x-landing.faq-item>
                    <x-landing.faq-item question="¿Mis datos se mezclan con los de otra empresa?">
                        No. Cada empresa tiene su propio espacio de datos aislado; ningún usuario puede ver información de una empresa distinta a la suya.
                    </x-landing.faq-item>
                    <x-landing.faq-item question="¿Puedo registrarme libremente para probar Fiscora?">
                        En este prototipo el acceso se gestiona de forma interna; no existe un registro público. Usa el botón "Iniciar sesión" con una cuenta ya creada.
                    </x-landing.faq-item>
                </div>
            </div>
        </section>

        <x-landing.footer />
    </div>
</x-layouts.auth>
