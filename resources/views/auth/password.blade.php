<x-layouts.auth title="Recuperar contraseña">
    <div class="min-h-screen flex items-center justify-center p-margin-mobile md:p-margin-desktop bg-surface">
        <section class="w-full max-w-md bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-xl">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-xs text-primary font-label-md text-label-md mb-lg">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Volver
            </a>

            <div class="w-12 h-12 rounded-xl ai-gradient text-on-primary flex items-center justify-center mb-md">
                <span class="material-symbols-outlined">lock_reset</span>
            </div>
            <h1 class="font-headline-lg-mobile text-headline-lg-mobile md:font-headline-lg md:text-headline-lg mb-xs">Recuperar contraseña</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mb-lg">Ingresa el correo asociado para preparar el flujo de recuperación.</p>

            <form class="space-y-md" data-demo-submit="password-alert">
                <div id="password-alert" class="hidden bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm">Solicitud registrada en modo prototipo.</div>
                <label class="block">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                    <input class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface focus:border-primary focus:ring focus:ring-primary/10" placeholder="correo@empresa.com" type="email" required>
                </label>
                <button class="w-full bg-primary text-on-primary rounded-lg py-md font-label-md text-label-md shadow-hover" type="submit">Enviar instrucciones</button>
            </form>
        </section>
    </div>
</x-layouts.auth>
