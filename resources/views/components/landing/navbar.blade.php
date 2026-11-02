{{--
    Public navbar for the premium landing — intentionally separate from
    the authenticated sidebar/topbar, since this page has no
    company/user context at all. Transparent over the dark hero by
    default; `data-landing-nav` is what landing.js toggles `.is-scrolled`
    on once the page scrolls past the hero (see app.css's `.landing-nav`
    rules — background/border/shadow only, nothing layout-affecting).
--}}
<header
    class="landing-nav fixed inset-x-0 top-0 z-30"
    data-landing-nav
>
    <nav class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop h-16 flex items-center justify-between gap-md">
        {{-- Real brand mark (same asset login's brand panel already uses
             on a dark surface), not a fabricated lettermark — sized and
             paired the same way sidebar.blade.php pairs its own icon +
             wordmark, so this reads as the existing Fiscora identity,
             not an improvised placeholder. --}}
        <a href="{{ route('home') }}" class="flex items-center gap-sm shrink-0">
            <img src="{{ asset('branding/fiscora-icon-transparent.png') }}" alt="Fiscora" class="h-9 w-9 object-contain shrink-0">
            <span class="landing-nav-word font-headline-md text-headline-md text-white leading-none">Fiscora</span>
        </a>

        <ul class="hidden lg:flex items-center gap-lg font-label-md text-label-md">
            <li><a href="#inicio" class="landing-nav-link">Inicio</a></li>
            <li><a href="#funcionalidades" class="landing-nav-link">Funcionalidades</a></li>
            <li><a href="#como-funciona" class="landing-nav-link">Cómo funciona</a></li>
            <li><a href="#seguridad" class="landing-nav-link">Seguridad</a></li>
        </ul>

        <div class="flex items-center gap-sm shrink-0">
            <a
                href="{{ route('login') }}"
                class="landing-btn landing-nav-ghost hidden sm:inline-flex items-center px-md py-sm rounded-full font-label-md text-label-md text-white border border-white/35 hover:border-white"
            >
                Iniciar sesión
            </a>
            <a
                href="#producto"
                class="landing-btn inline-flex items-center gap-xs px-md py-sm rounded-full bg-brand-gold text-primary font-label-md text-label-md font-semibold"
            >
                Explorar
                <span class="landing-btn-arrow">→</span>
            </a>
        </div>
    </nav>
</header>
