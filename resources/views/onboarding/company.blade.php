<x-layouts.auth title="Configuración inicial">
    <div class="min-h-screen bg-background">
        <header class="h-16 bg-surface-container-lowest border-b border-outline-variant/40 px-margin-mobile md:px-margin-desktop flex items-center justify-between">
            <div class="flex items-center gap-sm text-primary">
                <span class="material-symbols-outlined" data-fill="true">corporate_fare</span>
                <span class="font-title-lg text-title-lg">Configuración inicial</span>
            </div>
            <a href="{{ route('dashboard') }}" class="font-label-md text-label-md text-primary">Omitir por ahora</a>
        </header>

        <main class="max-w-[1100px] mx-auto p-margin-mobile md:p-margin-desktop grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-lg">
            <form class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg space-y-lg" data-demo-submit="onboarding-alert">
                <div>
                    <p class="font-label-sm text-label-sm text-primary uppercase mb-xs">Empresa</p>
                    <h1 class="font-headline-lg-mobile md:font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface">Datos base de la empresa</h1>
                    <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Configura la información mínima para comenzar el prototipo.</p>
                </div>

                <div id="onboarding-alert" class="hidden bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm">Configuración lista en modo visual.</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    @foreach ([['Razón social', 'FacturaPro Demo SAS'], ['NIT', '900.000.111-2'], ['Correo', 'admin@empresa.com.co'], ['Ciudad', 'Bucaramanga'], ['Prefijo de factura', 'FV'], ['Impuesto por defecto', 'IVA 19%']] as [$label, $value])
                        <label>
                            <span class="block font-label-md text-label-md text-on-surface mb-xs">{{ $label }}</span>
                            <input class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface focus:border-primary focus:ring focus:ring-primary/10" value="{{ $value }}">
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <button class="bg-primary text-on-primary rounded-lg px-lg py-sm font-label-md text-label-md shadow-hover" type="submit">Guardar configuración</button>
                </div>
            </form>

            <x-assistant-panel />
        </main>
    </div>
</x-layouts.auth>
