<x-layouts.app title="Datos de empresa" active="settings">
    <x-page-header eyebrow="Configuración" title="Datos de empresa" description="Información general usada por el prototipo de facturación simulada." />

    <x-settings-tabs />

    <form class="space-y-lg" method="POST" action="{{ route('settings.company.update') }}">
        @csrf
        @method('PUT')

        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Identificación</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Razón social</span>
                    <input name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Nombre comercial</span>
                    <input name="name" value="{{ old('name', $company->name) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de persona</span>
                    <select name="person_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                        @foreach (['natural' => 'Persona natural', 'juridica' => 'Persona jurídica'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('person_type', $company->person_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">NIT</span>
                    <div class="flex gap-sm">
                        <input name="nit" value="{{ old('nit', $company->nit) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text" placeholder="Solo números">
                        <span class="px-md py-sm rounded-lg bg-surface-container text-on-surface-variant font-mono" title="Dígito de verificación calculado">{{ $company->nit_dv ?? '-' }}</span>
                    </div>
                </label>
            </div>
        </section>

        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Contacto y ubicación</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                    <input name="email" value="{{ old('email', $company->email) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="email">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Teléfono</span>
                    <input name="phone" value="{{ old('phone', $company->phone) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label class="md:col-span-2">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Dirección</span>
                    <input name="address" value="{{ old('address', $company->address) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Ciudad</span>
                    <input name="city" value="{{ old('city', $company->city) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Departamento</span>
                    <input name="department" value="{{ old('department', $company->department) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">País</span>
                    <input name="country" value="{{ old('country', $company->country) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
            </div>
        </section>

        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Datos fiscales</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Régimen tributario</span>
                    <input name="tax_regime" value="{{ old('tax_regime', $company->tax_regime) }}" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tributo principal</span>
                    <select name="main_tax_id" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                        <option value="">Sin definir</option>
                        @foreach (\App\Models\Tax::query()->currentlyValid()->availableFor($company->id)->get() as $tax)
                            <option value="{{ $tax->id }}" @selected(old('main_tax_id', $company->main_tax_id) == $tax->id)>{{ $tax->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Moneda base</span>
                    <select name="currency" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                        @foreach (\App\Models\Company::CURRENCIES as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', $company->currency) === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-sm">
                    <input type="checkbox" name="is_test_environment" value="1" @checked(old('is_test_environment', $company->is_test_environment)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                    <span class="font-label-md text-label-md text-on-surface">Entorno de prueba (simulado)</span>
                </label>
                <div class="md:col-span-2">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Responsabilidades fiscales</span>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-xs">
                        @foreach (\App\Models\Company::FISCAL_RESPONSIBILITIES as $code => $label)
                            <label class="flex items-center gap-sm">
                                <input type="checkbox" name="fiscal_responsibilities[]" value="{{ $code }}" @checked(in_array($code, old('fiscal_responsibilities', $company->fiscal_responsibilities ?? []), true)) class="rounded border-outline-variant text-primary focus:ring-primary/20">
                                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Guardar cambios
            </button>
        </div>
    </form>
</x-layouts.app>
