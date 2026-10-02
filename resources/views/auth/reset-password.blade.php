<x-layouts.auth title="Restablecer contraseña">
    <div class="min-h-screen flex items-center justify-center p-margin-mobile md:p-margin-desktop bg-surface">
        <section class="w-full max-w-[28rem] bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-xl">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-xs text-primary font-label-md text-label-md mb-lg">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Volver
            </a>

            <div class="w-12 h-12 rounded-xl ai-gradient text-on-primary flex items-center justify-center mb-md">
                <span class="material-symbols-outlined">lock_reset</span>
            </div>
            <h1 class="font-headline-lg-mobile text-headline-lg-mobile md:font-headline-lg md:text-headline-lg mb-xs">Restablecer contraseña</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mb-lg">Ingresa una nueva contraseña para tu cuenta.</p>

            @if ($errors->any())
                <div class="bg-error-container border border-error/20 p-md rounded-lg flex items-start gap-sm mb-md">
                    <span class="material-symbols-outlined text-error mt-xs">error</span>
                    <p class="font-body-sm text-body-sm text-on-error-container">{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="space-y-md" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <label class="block">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                    <input class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface focus:border-primary focus:ring focus:ring-primary/10" name="email" value="{{ old('email', $email) }}" placeholder="correo@empresa.com" type="email" required>
                </label>

                <label class="block">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Nueva contraseña</span>
                    <input class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface focus:border-primary focus:ring focus:ring-primary/10" name="password" placeholder="Ingresa tu nueva contraseña" type="password" required>
                </label>

                <label class="block">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Confirmar contraseña</span>
                    <input class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface focus:border-primary focus:ring focus:ring-primary/10" name="password_confirmation" placeholder="Repite tu nueva contraseña" type="password" required>
                </label>

                <button class="w-full bg-primary text-on-primary rounded-lg py-md font-label-md text-label-md shadow-hover" type="submit">Guardar nueva contraseña</button>
            </form>
        </section>
    </div>
</x-layouts.auth>
