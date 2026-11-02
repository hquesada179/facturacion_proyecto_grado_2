<x-layouts.auth title="Inicio de sesión">
    <x-auth-premium-layout>
        <div class="mb-xl" data-reveal style="transition-delay:80ms">
            <h2 class="auth-premium-form-title">Bienvenido</h2>
            <p class="auth-premium-form-subtitle">Ingresa a tu cuenta para continuar.</p>
        </div>

        @if ($errors->any())
            <div class="auth-premium-error" data-reveal style="transition-delay:120ms">
                <span class="material-symbols-outlined mt-xs">error</span>
                <p class="font-body-sm text-body-sm">{{ $errors->first() }}</p>
            </div>
        @endif

        <form class="auth-premium-form" method="POST" action="{{ route('login') }}">
            @csrf
            <label class="auth-premium-field" data-reveal style="transition-delay:160ms">
                <span class="auth-premium-label">Correo electrónico</span>
                <span class="relative block">
                    <span class="material-symbols-outlined auth-premium-input-icon">mail</span>
                    <input class="auth-premium-input" name="email" value="{{ old('email') }}" placeholder="correo@empresa.com" required type="email" autocomplete="email">
                </span>
            </label>

            <label class="auth-premium-field" data-reveal style="transition-delay:200ms">
                <span class="auth-premium-label">Contraseña</span>
                <span class="relative block">
                    <span class="material-symbols-outlined auth-premium-input-icon">lock</span>
                    <input id="password" class="auth-premium-input auth-premium-input-pw" name="password" placeholder="Ingresa tu contraseña" required type="password" autocomplete="current-password">
                    <button class="auth-premium-toggle-pw" data-toggle-password="password" type="button" aria-label="Mostrar contraseña">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </span>
            </label>

            <div class="auth-premium-row" data-reveal style="transition-delay:240ms">
                <label class="auth-premium-remember">
                    <input class="h-4 w-4 text-primary focus:ring-primary border-outline-variant rounded bg-surface-container-lowest" type="checkbox" name="remember">
                    Recordarme
                </label>
                <a class="auth-premium-forgot" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            </div>

            <button class="auth-premium-submit" type="submit" data-reveal style="transition-delay:280ms">Iniciar sesión</button>
        </form>

        <div class="auth-premium-secure" data-reveal style="transition-delay:320ms">
            <span class="material-symbols-outlined text-[16px]">shield</span>
            Acceso seguro a Fiscora
        </div>
    </x-auth-premium-layout>
</x-layouts.auth>
