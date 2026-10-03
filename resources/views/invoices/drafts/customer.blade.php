<x-layouts.app title="Nueva factura" active="invoices">
    <x-page-header eyebrow="Facturas" title="Nueva factura" description="Selecciona o registra el cliente antes de agregar productos." />

    <x-invoice-stepper :current="1" :invoice="$invoice" />

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Cliente seleccionado</h3>
        @if ($invoice->customer)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Nombre</p>
                    <p class="font-body-md text-body-md text-on-surface">{{ $invoice->customer->name }}</p>
                </div>
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Identificación</p>
                    <p class="font-body-md text-body-md text-on-surface">{{ $invoice->customer->identification_type }} {{ $invoice->customer->identification_number }}</p>
                </div>
                <div>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Correo</p>
                    <p class="font-body-md text-body-md text-on-surface">{{ $invoice->customer->email ?? '-' }}</p>
                </div>
            </div>
        @else
            <p class="font-body-sm text-body-sm text-on-surface-variant">Todavía no se ha seleccionado ningún cliente.</p>
        @endif
    </section>

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Seleccionar cliente existente</h3>
        <form method="POST" action="{{ route('invoices.draft.customer.update', $invoice) }}" class="flex flex-col sm:flex-row gap-sm">
            @csrf
            <select name="customer_id" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected($invoice->customer_id === $customer->id)>
                        {{ $customer->name }} — {{ $customer->identification_type }} {{ $customer->identification_number }}
                    </option>
                @endforeach
            </select>
            <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover whitespace-nowrap" type="submit">Usar este cliente</button>
        </form>

        @if ($finalConsumer)
            <form method="POST" action="{{ route('invoices.draft.customer.update', $invoice) }}" class="mt-sm">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $finalConsumer->id }}">
                <button class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md" type="submit">Usar consumidor final</button>
            </form>
        @endif
    </section>

    <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg mb-lg">
        <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Crear cliente nuevo</h3>
        <form method="POST" action="{{ route('invoices.draft.customer.quick-create', $invoice) }}" class="space-y-md">
            @csrf
            <input type="hidden" name="status" value="active">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de persona</span>
                    <select name="person_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        <option value="natural">Persona natural</option>
                        <option value="juridica">Persona jurídica</option>
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Nombre o razón social</span>
                    <input name="name" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Tipo de identificación</span>
                    <select name="identification_type" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md">
                        @foreach (\App\Models\Customer::IDENTIFICATION_TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Número de identificación</span>
                    <input name="identification_number" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Correo electrónico</span>
                    <input name="email" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="email">
                </label>
                <label>
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Teléfono</span>
                    <input name="phone" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
                <label class="md:col-span-2">
                    <span class="block font-label-md text-label-md text-on-surface mb-xs">Dirección</span>
                    <input name="address" class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest font-body-md text-body-md" type="text">
                </label>
            </div>
            <div class="flex justify-end">
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover" type="submit">Crear y usar este cliente</button>
            </div>
        </form>
    </section>

    <div class="flex justify-end">
        <a href="{{ route('invoices.draft.items', $invoice) }}" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover">Continuar a productos</a>
    </div>
</x-layouts.app>
