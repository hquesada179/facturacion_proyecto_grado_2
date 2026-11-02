<x-layouts.auth title="Verifica tu correo">
    <x-auth-premium-layout>
        <x-slot:brandHeadline>
            <h1 class="auth-premium-headline" data-reveal style="transition-delay:110ms">Ya casi <em class="text-brand-gold not-italic">estás dentro.</em></h1>
        </x-slot:brandHeadline>
        <x-slot:brandSubtitle>
            <p class="font-body-md text-body-md text-white/80 max-w-[28rem] mb-xl" data-reveal style="transition-delay:200ms">Confirma tu correo para proteger el espacio de tu empresa antes de empezar a configurar Fiscora.</p>
        </x-slot:brandSubtitle>
        <x-slot:brandVisual>
            <x-auth.flow-steps current="verify" />
        </x-slot:brandVisual>

        @if ($state === 'success')
            <div class="text-center" data-reveal style="transition-delay:80ms">
                <div class="onboarding-complete-icon">
                    <span class="material-symbols-outlined">check</span>
                </div>
                <h2 class="auth-premium-form-title">Correo verificado.</h2>
                <p class="auth-premium-form-subtitle mb-lg">Vamos a configurar Fiscora. El siguiente paso es el asistente de configuración que ya conoces.</p>
                <a href="{{ route('onboarding.show') }}" class="auth-premium-submit" style="display:flex;align-items:center;justify-content:center;text-decoration:none;">
                    Configurar Fiscora
                </a>
            </div>
        @else
            <div class="mb-lg" data-reveal style="transition-delay:60ms">
                <h2 class="auth-premium-form-title">Verifica tu correo</h2>
                <p class="auth-premium-form-subtitle">Enviamos un código de 6 dígitos a tu correo. Ingrésalo para continuar.</p>
                <p class="font-body-sm text-body-sm text-on-surface font-semibold mt-sm">{{ $email }}</p>
            </div>

            @if ($otpError === 'invalid')
                <div class="auth-premium-error" data-reveal role="alert">
                    <span class="material-symbols-outlined mt-xs">error</span>
                    <p class="font-body-sm text-body-sm">Código incorrecto. Verifica e intenta de nuevo.</p>
                </div>
            @elseif ($otpError === 'locked')
                <div class="auth-premium-error" data-reveal role="alert">
                    <span class="material-symbols-outlined mt-xs">block</span>
                    <p class="font-body-sm text-body-sm">Demasiados intentos con este código. Solicita uno nuevo.</p>
                </div>
            @elseif ($otpError === 'expired')
                <div class="auth-premium-warning" data-reveal role="alert">
                    <span class="material-symbols-outlined mt-xs">schedule</span>
                    <p class="font-body-sm text-body-sm">Este código expiró. Solicita uno nuevo para continuar.</p>
                </div>
            @endif

            @if (session('status') === 'resent')
                <div class="auth-premium-status" data-reveal role="status">
                    <span class="material-symbols-outlined mt-xs">mail</span>
                    <p class="font-body-sm text-body-sm">Código reenviado a tu correo.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('verification.verify') }}" data-otp-form data-protect-submit data-reveal style="transition-delay:120ms">
                @csrf

                <div class="otp-inputs" data-otp-inputs role="group" aria-label="Código de verificación de 6 dígitos">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            class="otp-input {{ in_array($otpError, ['invalid', 'locked'], true) ? 'has-error' : '' }}"
                            type="text"
                            inputmode="numeric"
                            autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                            maxlength="1"
                            aria-label="Dígito {{ $i + 1 }} de 6"
                        >
                    @endfor
                </div>
                <input type="hidden" name="code" data-otp-hidden>

                <div class="auth-premium-resend-row">
                    <span>¿No llegó? <button type="submit" form="otp-resend-form" data-resend-trigger @disabled($cooldownRemaining > 0)>Reenviar código</button></span>
                    <span>Reenvío en <span data-otp-timer data-seconds="{{ $cooldownRemaining }}">{{ sprintf('%02d:%02d', intdiv($cooldownRemaining, 60), $cooldownRemaining % 60) }}</span></span>
                </div>

                <button class="auth-premium-submit" type="submit">
                    <span data-submit-label>Verificar correo</span>
                </button>
            </form>

            <form id="otp-resend-form" method="POST" action="{{ route('verification.send') }}" class="hidden">
                @csrf
            </form>

            <p class="text-center font-label-md text-label-md text-on-surface-variant mt-lg" data-reveal style="transition-delay:150ms">
                ¿Correo equivocado?
                <a
                    href="{{ route('logout') }}"
                    class="auth-premium-forgot"
                    onclick="event.preventDefault(); document.getElementById('otp-logout-form').submit();"
                >Cerrar sesión y volver</a>
            </p>
            <form id="otp-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                @csrf
            </form>
        @endif
    </x-auth-premium-layout>
</x-layouts.auth>
