{{-- Dark navy footer matching the approved artifact's "stamp" treatment
     for the academic-prototype disclaimer — a bordered gold-tinted box,
     not a plain paragraph, so it reads as a deliberate notice rather
     than legal fine print. --}}
<footer class="bg-[var(--landing-primary-deep)] text-white">
    <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop pt-xl md:pt-[4.5rem] pb-xl">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-xl pb-xl border-b border-white/12">
            <div class="max-w-[24rem]">
                <div class="flex items-center gap-sm mb-sm">
                    <img src="{{ asset('branding/fiscora-icon-transparent.png') }}" alt="Fiscora" class="h-9 w-9 object-contain shrink-0">
                    <span class="font-headline-md text-headline-md leading-none">Fiscora</span>
                </div>
                <p class="font-body-sm text-body-sm text-white/68">Plataforma de facturación simulada, trazable y asistida por IA — proyecto de grado académico.</p>
            </div>

            <nav class="flex flex-wrap gap-lg font-label-md text-label-md text-white/72">
                <a href="#inicio" class="hover:text-[var(--landing-gold-soft)] transition-colors">Inicio</a>
                <a href="#funcionalidades" class="hover:text-[var(--landing-gold-soft)] transition-colors">Funcionalidades</a>
                <a href="#como-funciona" class="hover:text-[var(--landing-gold-soft)] transition-colors">Cómo funciona</a>
                <a href="#seguridad" class="hover:text-[var(--landing-gold-soft)] transition-colors">Seguridad</a>
                <a href="{{ route('login') }}" class="hover:text-[var(--landing-gold-soft)] transition-colors">Iniciar sesión</a>
            </nav>
        </div>

        <div class="flex items-start gap-sm mt-xl mb-lg p-md rounded-lg border border-brand-gold/45 bg-brand-gold/8">
            <span class="material-symbols-outlined text-[var(--landing-gold-soft)] text-[18px] shrink-0">shield</span>
            <p class="font-label-md text-label-md text-[var(--landing-gold-soft)]">
                Prototipo académico. Los documentos generados son simulados y no tienen validez tributaria.
            </p>
        </div>

        <p class="font-label-sm text-label-sm text-white/45">&copy; {{ now()->year }} Fiscora · Proyecto de grado académico.</p>
    </div>
</footer>
