<x-layouts.auth title="Recuperar contraseña">
    <x-auth-premium-layout>
        <a href="{{ route('login') }}" class="auth-premium-back-link mb-lg" data-reveal style="transition-delay:40ms">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Volver a iniciar sesión
        </a>

        <div class="mb-xl" data-reveal style="transition-delay:80ms">
            <h2 class="auth-premium-form-title">Recuperar contraseña</h2>
            <p class="auth-premium-form-subtitle">Ingresa el correo asociado a tu cuenta y te enviaremos las instrucciones para restablecer tu contraseña.</p>
        </div>

        @if (session('status'))
            <div class="auth-premium-status" data-reveal style="transition-delay:120ms">
                <span class="material-symbols-outlined mt-xs">info</span>
                <p class="font-body-sm text-body-sm">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="auth-premium-error" data-reveal style="transition-delay:120ms">
                <span class="material-symbols-outlined mt-xs">error</span>
                <p class="font-body-sm text-body-sm">{{ $errors->first() }}</p>
            </div>
        @endif

        <form class="auth-premium-form" method="POST" action="{{ route('password.email') }}">
            @csrf
            <label class="auth-premium-field" data-reveal style="transition-delay:160ms">
                <span class="auth-premium-label">Correo electrónico</span>
                <span class="relative block">
                    <span class="material-symbols-outlined auth-premium-input-icon">mail</span>
                    <input class="auth-premium-input" name="email" value="{{ old('email') }}" placeholder="correo@empresa.com" required type="email" autocomplete="email">
                </span>
            </label>

            <button class="auth-premium-submit" type="submit" data-reveal style="transition-delay:200ms">Enviar instrucciones</button>
        </form>

        <div class="auth-premium-secure" data-reveal style="transition-delay:240ms">
            <span class="material-symbols-outlined text-[16px]">shield</span>
            Recuperación segura de acceso
        </div>
    </x-auth-premium-layout>
</x-layouts.auth>
