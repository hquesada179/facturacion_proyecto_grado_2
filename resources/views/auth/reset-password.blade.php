<x-layouts.auth title="Restablecer contraseña">
    <x-auth-split-layout>
        <a href="{{ route('login') }}" class="inline-flex items-center gap-xs text-primary font-label-md text-label-md mb-lg">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Volver
        </a>

        <div class="mb-lg">
            <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface mb-xs">Restablecer contraseña</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Ingresa una nueva contraseña para tu cuenta.</p>
        </div>

        @if ($errors->any())
            <div class="bg-error-container border border-error/20 p-md rounded-lg flex items-start gap-sm mb-lg">
                <span class="material-symbols-outlined text-error mt-xs">error</span>
                <p class="font-body-sm text-body-sm text-on-error-container">{{ $errors->first() }}</p>
            </div>
        @endif

        <form class="space-y-md" method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label class="block">
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                <input class="w-full px-md py-md rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface placeholder:text-outline focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-body-md text-body-md" name="email" value="{{ old('email', $email) }}" placeholder="correo@empresa.com" type="email" required>
            </label>

            <label class="block">
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Nueva contraseña</span>
                <input class="w-full px-md py-md rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface placeholder:text-outline focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-body-md text-body-md" name="password" placeholder="Ingresa tu nueva contraseña" type="password" required>
            </label>

            <label class="block">
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Confirmar contraseña</span>
                <input class="w-full px-md py-md rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface placeholder:text-outline focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all font-body-md text-body-md" name="password_confirmation" placeholder="Repite tu nueva contraseña" type="password" required>
            </label>

            <button class="w-full flex justify-center py-md px-lg border border-transparent rounded-lg shadow-sm font-label-md text-label-md text-on-primary bg-primary hover:bg-primary-container focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors hover:shadow-md" type="submit">Guardar nueva contraseña</button>
        </form>
    </x-auth-split-layout>
</x-layouts.auth>
