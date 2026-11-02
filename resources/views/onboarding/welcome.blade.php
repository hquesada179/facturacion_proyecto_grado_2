<x-layouts.auth title="Bienvenido a Fiscora">
    <div
        class="min-h-screen flex items-center justify-center text-center text-white relative overflow-hidden px-margin-mobile py-xl"
        style="background: linear-gradient(175deg, var(--color-primary) 0%, #081433 55%, #040c1f 100%);"
    >
        <div class="relative z-10 max-w-[28rem]">
            <div class="flex flex-col items-center gap-sm mb-xl" data-reveal>
                <img src="{{ asset('branding/fiscora-icon-transparent.png') }}" alt="Fiscora" class="h-12 w-12 object-contain">
                <span class="font-headline-md text-headline-md tracking-[0.1em]">FISCORA</span>
            </div>

            <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg mb-md" data-reveal style="transition-delay:60ms">
                Configuremos Fiscora para tu empresa
            </h1>
            <p class="font-body-md text-body-md text-white/80 mb-xl" data-reveal style="transition-delay:120ms">
                Solo necesitamos algunos datos antes de crear tu primera factura de prueba.
            </p>

            <div class="flex flex-col items-center gap-md" data-reveal style="transition-delay:180ms">
                <form method="POST" action="{{ route('onboarding.start') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-xs px-xl py-md rounded-full bg-brand-gold text-primary font-label-md text-label-md font-semibold shadow-[0_10px_24px_-8px_rgba(0,0,0,0.4)] hover:-translate-y-px transition-transform">
                        Comenzar
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                </form>
                <a href="{{ route('dashboard') }}" class="font-label-md text-label-md text-white/55 hover:text-white transition-colors">Configurar más tarde</a>
            </div>

            <div class="flex items-center justify-center gap-xs mt-xl font-label-sm text-label-sm text-white/45" data-reveal style="transition-delay:240ms">
                <span class="material-symbols-outlined text-[15px]">shield</span>
                Prototipo académico · Documentos de prueba sin validez tributaria real
            </div>
        </div>
    </div>
</x-layouts.auth>
