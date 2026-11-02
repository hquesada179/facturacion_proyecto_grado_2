<x-layouts.auth title="Onboarding — Cliente y producto">
    <x-auth-premium-layout>
        <x-slot:brandVisual>
            <x-onboarding.step-list current="data" />
        </x-slot:brandVisual>

        <div class="mb-xl" data-reveal style="transition-delay:80ms">
            <h2 class="auth-premium-form-title">Tu primer cliente y producto</h2>
            <p class="auth-premium-form-subtitle">Así tendrás todo listo para crear tu primera factura en el tutorial.</p>
        </div>

        @if ($errors->any())
            <div class="auth-premium-error" data-reveal style="transition-delay:100ms">
                <span class="material-symbols-outlined mt-xs">error</span>
                <p class="font-body-sm text-body-sm">{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="auth-premium-data-choice" data-reveal style="transition-delay:120ms" id="onboarding-data-choice">
            <div class="auth-premium-data-option selected" data-choice="demo">
                <div class="auth-premium-data-option-top">
                    <span class="material-symbols-outlined">auto_awesome</span>
                    <span class="auth-premium-data-option-title">Usar datos de demostración</span>
                    <span class="auth-premium-data-radio"></span>
                </div>
                <p class="auth-premium-data-option-desc">Creamos un cliente y un producto de ejemplo para que empieces de inmediato.</p>
            </div>
            <div class="auth-premium-data-option" data-choice="own">
                <div class="auth-premium-data-option-top">
                    <span class="material-symbols-outlined">group</span>
                    <span class="auth-premium-data-option-title">Crear mis propios datos</span>
                    <span class="auth-premium-data-radio"></span>
                </div>
                <p class="auth-premium-data-option-desc">Ingresas tú mismo el cliente y el producto reales de tu empresa.</p>
            </div>
        </div>

        {{-- ===================== DEMO ===================== --}}
        <div id="onboarding-demo-panel" data-reveal style="transition-delay:140ms">
            <div class="auth-premium-fieldgroup">
                <p class="auth-premium-fieldgroup-title"><span class="material-symbols-outlined">group</span>Cliente inicial</p>
                @if ($hasDemoCustomer)
                    <div class="auth-premium-mini-card mb-sm">
                        <div class="auth-premium-mini-card-row"><span class="k">Nombre / razón social</span><span class="v">Comercial Andina SAS</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Identificación</span><span class="v">NIT {{ \App\Services\Onboarding\OnboardingService::DEMO_CUSTOMER_IDENTIFICATION }}</span></div>
                    </div>
                    <p class="font-label-sm text-label-sm" style="color:var(--color-success-soft-foreground);">✓ Cliente de demostración ya creado.</p>
                @else
                    <div class="auth-premium-mini-card mb-sm">
                        <div class="auth-premium-mini-card-row"><span class="k">Nombre / razón social</span><span class="v">Comercial Andina SAS</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Identificación</span><span class="v">NIT 900555123</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Correo</span><span class="v">contacto@andina.com</span></div>
                    </div>
                    <form method="POST" action="{{ route('onboarding.customer.demo') }}">
                        @csrf
                        <button type="submit" class="auth-premium-submit" style="width:auto;padding:0.6rem 1.2rem;font-size:13px;">Crear cliente de demostración</button>
                    </form>
                @endif
            </div>

            <div class="auth-premium-fieldgroup">
                <p class="auth-premium-fieldgroup-title"><span class="material-symbols-outlined">inventory_2</span>Producto o servicio inicial</p>
                @if ($hasDemoProduct)
                    <div class="auth-premium-mini-card mb-sm">
                        <div class="auth-premium-mini-card-row"><span class="k">Código</span><span class="v">{{ \App\Services\Onboarding\OnboardingService::DEMO_PRODUCT_SKU }}</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Nombre</span><span class="v">Asesoría contable mensual</span></div>
                    </div>
                    <p class="font-label-sm text-label-sm" style="color:var(--color-success-soft-foreground);">✓ Producto de demostración ya creado.</p>
                @else
                    <div class="auth-premium-mini-card mb-sm">
                        <div class="auth-premium-mini-card-row"><span class="k">Código</span><span class="v">SERV-001</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Nombre</span><span class="v">Asesoría contable mensual</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Tipo</span><span class="v">Servicio</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Precio</span><span class="v">$ 450.000,00</span></div>
                        <div class="auth-premium-mini-card-row"><span class="k">Impuesto</span><span class="v">IVA 19%</span></div>
                    </div>
                    <form method="POST" action="{{ route('onboarding.product.demo') }}">
                        @csrf
                        <button type="submit" class="auth-premium-submit" style="width:auto;padding:0.6rem 1.2rem;font-size:13px;">Crear producto de demostración</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ===================== OWN DATA ===================== --}}
        <div id="onboarding-own-panel" style="display:none;">
            <div class="auth-premium-fieldgroup">
                <p class="auth-premium-fieldgroup-title"><span class="material-symbols-outlined">group</span>Cliente inicial</p>
                @if ($hasCustomer)
                    <p class="font-label-sm text-label-sm" style="color:var(--color-success-soft-foreground);">✓ Ya tienes al menos un cliente registrado.</p>
                @else
                    <form method="POST" action="{{ route('onboarding.customer.store') }}" class="auth-premium-fieldgrid">
                        @csrf
                        <input type="hidden" name="person_type" value="natural">
                        <input type="hidden" name="identification_type" value="NIT">
                        <input type="hidden" name="status" value="active">
                        <label class="auth-premium-field span2">
                            <span class="auth-premium-label">Nombre / razón social<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" name="name" placeholder="Nombre del cliente" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Identificación (NIT)<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" name="identification_number" placeholder="900123456" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Correo<span class="auth-premium-opt">Opcional</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" type="email" name="email" placeholder="correo@cliente.com">
                        </label>
                        <div class="span2">
                            <button type="submit" class="auth-premium-submit" style="width:auto;padding:0.6rem 1.2rem;font-size:13px;">Crear cliente</button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="auth-premium-fieldgroup">
                <p class="auth-premium-fieldgroup-title"><span class="material-symbols-outlined">inventory_2</span>Producto o servicio inicial</p>
                @if ($hasProduct)
                    <p class="font-label-sm text-label-sm" style="color:var(--color-success-soft-foreground);">✓ Ya tienes al menos un producto registrado.</p>
                @else
                    <form method="POST" action="{{ route('onboarding.product.store') }}" class="auth-premium-fieldgrid">
                        @csrf
                        <input type="hidden" name="status" value="active">
                        <input type="hidden" name="unit" value="unidad">
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Código<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" name="sku" placeholder="PROD-001" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Nombre<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" name="name" placeholder="Nombre del producto" required>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Tipo<span class="auth-premium-req">Requerido</span></span>
                            <select class="auth-premium-input" style="padding-left:1rem;" name="type" required>
                                <option value="product">Producto</option>
                                <option value="service" selected>Servicio</option>
                            </select>
                        </label>
                        <label class="auth-premium-field">
                            <span class="auth-premium-label">Precio<span class="auth-premium-req">Requerido</span></span>
                            <input class="auth-premium-input" style="padding-left:1rem;" type="number" step="0.01" min="0" name="price" placeholder="0.00" required>
                        </label>
                        <label class="auth-premium-field span2">
                            <span class="auth-premium-label">Impuesto<span class="auth-premium-opt">Opcional</span></span>
                            <select class="auth-premium-input" style="padding-left:1rem;" name="taxes[]">
                                <option value="">Sin impuesto</option>
                                @foreach ($taxes as $tax)
                                    <option value="{{ $tax->id }}">{{ $tax->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="span2">
                            <button type="submit" class="auth-premium-submit" style="width:auto;padding:0.6rem 1.2rem;font-size:13px;">Crear producto</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-between mt-lg pt-lg" style="border-top:1px solid var(--color-outline-variant);">
            <a href="{{ route('onboarding.show', ['step' => 'billing']) }}" class="auth-premium-back-link">Volver</a>
            @if ($hasCustomer && $hasProduct)
                <form method="POST" action="{{ route('onboarding.data.continue') }}">
                    @csrf
                    <button type="submit" class="auth-premium-submit" style="width:auto;padding-left:1.5rem;padding-right:1.5rem;">Continuar</button>
                </form>
            @else
                <span class="auth-premium-opt" style="font-size:11.5px;">Crea un cliente y un producto para continuar</span>
            @endif
        </div>

        <script>
            (function () {
                var options = document.querySelectorAll('#onboarding-data-choice .auth-premium-data-option');
                var demoPanel = document.getElementById('onboarding-demo-panel');
                var ownPanel = document.getElementById('onboarding-own-panel');

                options.forEach(function (option) {
                    option.addEventListener('click', function () {
                        options.forEach(function (o) { o.classList.remove('selected'); });
                        option.classList.add('selected');
                        var isDemo = option.dataset.choice === 'demo';
                        demoPanel.style.display = isDemo ? '' : 'none';
                        ownPanel.style.display = isDemo ? 'none' : '';
                    });
                });
            })();
        </script>
    </x-auth-premium-layout>
</x-layouts.auth>
