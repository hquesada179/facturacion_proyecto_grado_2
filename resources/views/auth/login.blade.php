<x-layouts.auth title="Inicio de sesión">
    <x-auth-split-layout>
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
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">mail</span>
                    <input class="block w-full pl-[3rem] pr-md py-md bg-surface-container-lowest border border-outline-variant rounded-lg text-on-surface placeholder:text-outline focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md" name="email" value="{{ old('email') }}" placeholder="correo@empresa.com" required type="email">
                </span>
            </label>

            <label class="block">
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Contraseña</span>
                <span class="relative block">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">lock</span>
                    <input id="password" class="block w-full pl-[3rem] pr-[3rem] py-md bg-surface-container-lowest border border-outline-variant rounded-lg text-on-surface placeholder:text-outline focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md" name="password" placeholder="Ingresa tu contraseña" required type="password">
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface p-xs" data-toggle-password="password" type="button" aria-label="Mostrar contraseña">
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
    </x-auth-split-layout>
</x-layouts.auth>
