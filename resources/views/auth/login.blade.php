<x-layouts.auth title="Inicio de sesión">
    <div class="flex min-h-screen flex-col md:flex-row">
        <section class="hidden md:flex md:w-1/2 bg-primary text-on-primary flex-col justify-between p-margin-desktop relative overflow-hidden">
            <div class="absolute top-0 right-0 w-[520px] h-[520px] bg-primary-container rounded-full opacity-20 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-[360px] h-[360px] bg-secondary rounded-full opacity-20 blur-3xl"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-sm mb-xl">
                    <span class="material-symbols-outlined text-title-lg" data-fill="true">receipt_long</span>
                    <span class="font-headline-md text-headline-md">Facturación Pro</span>
                </div>
                <h1 class="font-display-lg text-display-lg mb-md">Sistema de Facturación Inteligente</h1>
                <p class="font-body-lg text-body-lg opacity-90 max-w-[28rem] mb-xl">Facturación electrónica simulada para pymes con acompañamiento inteligente durante cada proceso.</p>

                <div class="space-y-lg">
                    @foreach ([['route', 'Facturación guiada'], ['verified_user', 'Validación de información'], ['smart_toy', 'Asistente IA integrado']] as [$icon, $text])
                        <div class="flex items-start gap-md">
                            <div class="bg-primary-container p-sm rounded-lg flex items-center justify-center">
                                <span class="material-symbols-outlined">{{ $icon }}</span>
                            </div>
                            <p class="font-title-lg text-title-lg">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="flex-1 flex flex-col justify-center items-center p-margin-mobile md:p-margin-desktop bg-surface relative">
            <div class="md:hidden flex items-center gap-sm mb-xl w-full max-w-[24rem] text-primary">
                <span class="material-symbols-outlined text-title-lg" data-fill="true">receipt_long</span>
                <span class="font-headline-md text-headline-md">Facturación Pro</span>
            </div>

            <div class="w-full max-w-[24rem]">
                <div class="mb-lg">
                    <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface mb-xs">Bienvenido</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Ingresa a tu cuenta para continuar.</p>
                </div>

                @if ($errors->any())
                    <div class="bg-error-container border border-error/20 p-md rounded-lg flex items-start gap-sm mb-lg">
                        <span class="material-symbols-outlined text-error mt-xs">error</span>
                        <p class="font-body-sm text-body-sm text-on-error-container">{{ $errors->first() }}</p>
                    </div>
                @endif

                <form class="space-y-md" method="POST" action="{{ route('login') }}">
                    @csrf
                    <label class="block">
                        <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                        <span class="relative block">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">mail</span>
                            <input class="block w-full pl-xl pr-sm py-sm bg-surface-container-lowest border border-outline-variant rounded-lg text-on-surface placeholder:text-outline focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md" name="email" value="{{ old('email') }}" placeholder="correo@empresa.com" required type="email">
                        </span>
                    </label>

                    <label class="block">
                        <span class="block font-label-md text-label-md text-on-surface mb-xs">Contraseña</span>
                        <span class="relative block">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">lock</span>
                            <input id="password" class="block w-full pl-xl pr-xl py-sm bg-surface-container-lowest border border-outline-variant rounded-lg text-on-surface placeholder:text-outline focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md" name="password" placeholder="Ingresa tu contraseña" required type="password">
                            <button class="absolute right-2 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface p-xs" data-toggle-password="password" type="button" aria-label="Mostrar contraseña">
                                <span class="material-symbols-outlined">visibility</span>
                            </button>
                        </span>
                    </label>

                    <div class="flex items-center justify-between mt-sm">
                        <label class="flex items-center gap-sm font-body-sm text-body-sm text-on-surface-variant">
                            <input class="h-4 w-4 text-primary focus:ring-primary border-outline-variant rounded bg-surface-container-lowest" type="checkbox" name="remember">
                            Recordarme
                        </label>
                        <a class="font-label-md text-label-md text-primary hover:text-primary-container" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button class="w-full flex justify-center py-md px-lg border border-transparent rounded-lg shadow-sm font-label-md text-label-md text-on-primary bg-primary hover:bg-primary-container focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors hover:shadow-md" type="submit">Iniciar sesión</button>
                </form>
            </div>
        </section>
    </div>
</x-layouts.auth>
