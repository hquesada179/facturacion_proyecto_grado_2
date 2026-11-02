<x-layouts.auth title="Crear cuenta">
    <x-auth-premium-layout>
        <x-slot:brandHeadline>
            <h1 class="auth-premium-headline" data-reveal style="transition-delay:110ms">Empieza con <em class="text-brand-gold not-italic">Fiscora.</em></h1>
        </x-slot:brandHeadline>
        <x-slot:brandSubtitle>
            <p class="font-body-md text-body-md text-white/80 max-w-[28rem] mb-xl" data-reveal style="transition-delay:200ms">Facturación simulada, trazabilidad documental y un asistente IA contextual — en un espacio seguro y exclusivo para tu empresa.</p>
        </x-slot:brandSubtitle>
        <x-slot:brandVisual>
            <x-auth.flow-steps current="register" />
        </x-slot:brandVisual>

        <div class="mb-xl" data-reveal style="transition-delay:80ms">
            <h2 class="auth-premium-form-title">Crea tu cuenta</h2>
            <p class="auth-premium-form-subtitle">Empieza gratis. Configurarás tu empresa en el siguiente paso.</p>
        </div>

        @if ($errors->any())
            <div class="auth-premium-error" data-reveal style="transition-delay:100ms">
                <span class="material-symbols-outlined mt-xs">error</span>
                <p class="font-body-sm text-body-sm">{{ $errors->first() }}</p>
            </div>
        @endif

        <form class="auth-premium-form" method="POST" action="{{ route('register') }}" data-protect-submit>
            @csrf
            <label class="auth-premium-field" data-reveal style="transition-delay:140ms">
                <span class="auth-premium-label">Nombre completo</span>
                <span class="relative block">
                    <span class="material-symbols-outlined auth-premium-input-icon">person</span>
                    <input class="auth-premium-input" name="name" value="{{ old('name') }}" placeholder="Ej. María Fernanda Rojas" required type="text" autocomplete="name" autofocus>
                </span>
            </label>

            <label class="auth-premium-field" data-reveal style="transition-delay:170ms">
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
                    <input id="password" class="auth-premium-input auth-premium-input-pw" name="password" placeholder="Mínimo 8 caracteres" required type="password" autocomplete="new-password">
                    <button class="auth-premium-toggle-pw" data-toggle-password="password" type="button" aria-label="Mostrar contraseña">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </span>
            </label>

            <label class="auth-premium-field" data-reveal style="transition-delay:230ms">
                <span class="auth-premium-label">Confirmar contraseña</span>
                <span class="relative block">
                    <span class="material-symbols-outlined auth-premium-input-icon">lock</span>
                    <input id="password_confirmation" class="auth-premium-input auth-premium-input-pw" name="password_confirmation" placeholder="Repite tu contraseña" required type="password" autocomplete="new-password">
                    <button class="auth-premium-toggle-pw" data-toggle-password="password_confirmation" type="button" aria-label="Mostrar contraseña">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </span>
            </label>

            <label class="flex items-start gap-sm mt-sm" data-reveal style="transition-delay:260ms">
                <input class="h-4 w-4 mt-xs text-primary focus:ring-primary border-outline-variant rounded bg-surface-container-lowest" type="checkbox" name="terms" value="1" required>
                <span class="font-body-sm text-body-sm text-on-surface-variant">Acepto los <strong class="text-on-surface">Términos y condiciones</strong> y la <strong class="text-on-surface">Política de privacidad</strong> de Fiscora.</span>
            </label>

            <button class="auth-premium-submit" type="submit" data-reveal style="transition-delay:290ms">
                <span data-submit-label>Crear cuenta</span>
            </button>
        </form>

        <p class="text-center font-label-md text-label-md text-on-surface-variant mt-lg" data-reveal style="transition-delay:320ms">
            ¿Ya tienes una cuenta? <a href="{{ route('login') }}" class="auth-premium-forgot">Iniciar sesión</a>
        </p>

        <div class="auth-premium-secure" data-reveal style="transition-delay:350ms">
            <span class="material-symbols-outlined text-[16px]">shield</span>
            Tus datos quedan aislados por empresa
        </div>
    </x-auth-premium-layout>
</x-layouts.auth>
