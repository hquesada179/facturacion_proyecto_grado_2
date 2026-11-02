{{--
    Premium full-screen split shell — used by the login screen only.
    Deliberately separate from <x-auth-split-layout> (still used as-is by
    password.blade.php/reset-password.blade.php, which keep their current
    card-based look until that redesign is approved too).

    The brand panel is a fixed navy world — same reasoning as the landing
    hero and as the old auth-split-layout's own bg-primary panel: it does
    not repaint under the app's light/dark toggle. The form panel keeps
    using the app's own surface/on-surface tokens, so it continues to
    respond correctly to dark mode exactly like before.

    The left visual composition reuses <x-landing.dashboard-mockup />
    as-is — the same approved component already shown on the public
    landing — instead of duplicating its markup here.

    UI/UX 3.2 — three optional named slots (brandHeadline/brandSubtitle/
    brandVisual) let the onboarding wizard reuse this exact shell with
    its own step list instead of the dashboard mockup, without a second
    parallel layout component. Login/password screens pass none of
    these, so their rendered output is byte-for-byte unchanged.
--}}
<div class="auth-premium">
    {{-- Desktop/tablet brand panel --}}
    <aside class="auth-premium-brand" aria-hidden="true">
        <span class="landing-blob auth-premium-blob-a" style="width:300px;height:300px;background:var(--color-brand-gold);top:-80px;right:-30px;"></span>
        <span class="landing-blob auth-premium-blob-b" style="width:360px;height:360px;background:#2546a8;bottom:-130px;left:-50px;"></span>

        <div class="auth-premium-brand-top">
            <div class="auth-premium-logo" data-reveal>
                <img src="{{ asset('branding/fiscora-icon-transparent.png') }}" alt="Fiscora" class="h-11 w-11 object-contain shrink-0">
                <div>
                    <p class="font-headline-md text-headline-md tracking-[0.12em]">FISCORA</p>
                    <p class="font-label-sm text-label-sm text-brand-gold tracking-[0.18em] uppercase">Plataforma de Facturación</p>
                </div>
            </div>

            @isset($brandHeadline)
                {{ $brandHeadline }}
            @else
                <h1 class="auth-premium-headline" data-reveal style="transition-delay:110ms">Tu facturación, <em class="text-brand-gold not-italic">bajo control.</em></h1>
            @endisset

            @isset($brandSubtitle)
                {{ $brandSubtitle }}
            @else
                <p class="font-body-md text-body-md text-white/80 max-w-[28rem] mb-xl" data-reveal style="transition-delay:200ms">Centraliza facturación simulada, trazabilidad documental y un asistente IA contextual — todo en un solo lugar, pensado para tu empresa.</p>
            @endisset
        </div>

        @isset($brandVisual)
            {{ $brandVisual }}
        @else
            <div class="auth-premium-visual" data-reveal style="transition-delay:300ms">
                <x-landing.dashboard-mockup />
            </div>
        @endisset

        <p class="auth-premium-disclaimer" data-reveal style="transition-delay:400ms">Prototipo académico · Documentos de prueba sin validez tributaria</p>
    </aside>

    {{-- Compact mobile brand header --}}
    <div class="auth-premium-brand-mobile">
        <div class="auth-premium-logo-mobile">
            <img src="{{ asset('branding/fiscora-icon-transparent.png') }}" alt="Fiscora" class="h-8 w-8 object-contain shrink-0">
            <p class="font-headline-md text-headline-md tracking-[0.1em]">FISCORA</p>
        </div>
        <h1 class="auth-premium-headline-mobile">Tu facturación, <em class="text-brand-gold not-italic">bajo control.</em></h1>
        <p class="font-label-sm text-label-sm text-white/70">Facturación, trazabilidad y asistencia IA en un solo lugar.</p>
    </div>

    {{-- Form panel --}}
    <main class="auth-premium-form-panel">
        <nav class="auth-premium-nav">
            <div class="flex items-center gap-sm text-primary">
                <img src="{{ asset('branding/fiscora-icon.png') }}" alt="Fiscora" class="h-7 w-7 object-contain">
                <span class="font-headline-md text-headline-md">Fiscora</span>
            </div>
            <a href="{{ route('home') }}" class="auth-premium-back-link">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Volver al inicio
            </a>
        </nav>

        <div class="auth-premium-form-wrap">
            <div class="auth-premium-form-inner">
                {{ $slot }}
            </div>
        </div>
    </main>
</div>
