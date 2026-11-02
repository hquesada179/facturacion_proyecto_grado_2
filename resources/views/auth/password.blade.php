<x-layouts.auth title="Recuperar contraseña">
    <x-auth-split-layout>
        <a href="{{ route('login') }}" class="inline-flex items-center gap-xs text-primary font-label-md text-label-md mb-lg">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Volver
        </a>

        <div class="mb-lg">
            <h2 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface mb-xs">Recuperar contraseña</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Ingresa el correo asociado para preparar el flujo de recuperación.</p>
        </div>

        @if (session('status'))
            <div class="bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm mb-lg">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="bg-error-container border border-error/20 p-md rounded-lg flex items-start gap-sm mb-lg">
                <span class="material-symbols-outlined text-error mt-xs">error</span>
                <p class="font-body-sm text-body-sm text-on-error-container">{{ $errors->first() }}</p>
            </div>
        @endif

        <form class="space-y-md" method="POST" action="{{ route('password.email') }}">
            @csrf
            <label class="block">
                <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                <span class="relative block">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">mail</span>
                    <input class="block w-full pl-[3rem] pr-md py-md bg-surface-container-lowest border border-outline-variant rounded-lg text-on-surface placeholder:text-outline focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-body-md text-body-md" name="email" value="{{ old('email') }}" placeholder="correo@empresa.com" type="email" required>
                </span>
            </label>
            <button class="w-full flex justify-center py-md px-lg border border-transparent rounded-lg shadow-sm font-label-md text-label-md text-on-primary bg-primary hover:bg-primary-container focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors hover:shadow-md" type="submit">Enviar instrucciones</button>
        </form>
    </x-auth-split-layout>
</x-layouts.auth>
