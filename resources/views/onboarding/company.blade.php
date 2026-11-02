<x-layouts.auth title="Onboarding — Empresa">
    <x-auth-premium-layout>
        <x-slot:brandVisual>
            <x-onboarding.step-list current="company" />
        </x-slot:brandVisual>

        <div class="mb-xl" data-reveal style="transition-delay:80ms">
            <h2 class="auth-premium-form-title">Datos de tu empresa</h2>
            <p class="auth-premium-form-subtitle">Esta información ya existe en Configuración de Fiscora — aquí solo la confirmas una vez para dejar todo listo.</p>
        </div>

        @if ($errors->any())
            <div class="auth-premium-error" data-reveal style="transition-delay:100ms">
                <span class="material-symbols-outlined mt-xs">error</span>
                <p class="font-body-sm text-body-sm">{{ $errors->first() }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('onboarding.company.update') }}" data-reveal style="transition-delay:120ms">
            @csrf
            @method('PUT')

            <div class="auth-premium-fieldgroup">
                <p class="auth-premium-fieldgroup-title">
                    <span class="material-symbols-outlined">apartment</span>
                    Identificación
                </p>
                <div class="auth-premium-fieldgrid">
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Razón social<span class="auth-premium-req">Requerido</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" placeholder="Comercial Andina SAS" required>
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Nombre comercial<span class="auth-premium-opt">Opcional</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="name" value="{{ old('name', $company->name) }}" placeholder="Comercial Andina">
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">NIT<span class="auth-premium-req">Requerido</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="nit" value="{{ old('nit', $company->nit) }}" placeholder="900123456" required>
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Tipo de persona<span class="auth-premium-req">Requerido</span></span>
                        <select class="auth-premium-input" style="padding-left:1rem;" name="person_type" required>
                            @foreach (['juridica' => 'Persona jurídica', 'natural' => 'Persona natural'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('person_type', $company->person_type ?? 'juridica') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

            <div class="auth-premium-fieldgroup">
                <p class="auth-premium-fieldgroup-title">
                    <span class="material-symbols-outlined">contact_mail</span>
                    Contacto y ubicación
                </p>
                <div class="auth-premium-fieldgrid">
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Correo electrónico<span class="auth-premium-req">Requerido</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" type="email" name="email" value="{{ old('email', $company->email) }}" placeholder="contacto@empresa.com" required>
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Teléfono<span class="auth-premium-opt">Opcional</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="phone" value="{{ old('phone', $company->phone) }}" placeholder="601 555 0100">
                    </label>
                    <label class="auth-premium-field span2">
                        <span class="auth-premium-label">Dirección<span class="auth-premium-req">Requerido</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="address" value="{{ old('address', $company->address) }}" placeholder="Calle 10 # 5-23" required>
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Ciudad<span class="auth-premium-req">Requerido</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="city" value="{{ old('city', $company->city) }}" placeholder="Bogotá" required>
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">Departamento<span class="auth-premium-opt">Opcional</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="department" value="{{ old('department', $company->department) }}" placeholder="Bogotá D.C.">
                    </label>
                    <label class="auth-premium-field">
                        <span class="auth-premium-label">País<span class="auth-premium-req">Requerido</span></span>
                        <input class="auth-premium-input" style="padding-left:1rem;" name="country" value="{{ old('country', $company->country ?? 'Colombia') }}" required>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-between mt-lg pt-lg" style="border-top:1px solid var(--color-outline-variant);">
                <a href="{{ route('onboarding.show') }}" class="auth-premium-back-link">Volver</a>
                <button type="submit" class="auth-premium-submit" style="width:auto;padding-left:1.5rem;padding-right:1.5rem;">
                    Continuar
                </button>
            </div>
        </form>
    </x-auth-premium-layout>
</x-layouts.auth>
