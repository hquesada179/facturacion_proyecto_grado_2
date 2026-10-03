<x-layouts.app title="Clientes" active="customers">
    <x-page-header eyebrow="Clientes" title="Clientes" description="Administra los clientes reales de la empresa.">
        <x-slot:actions>
            @can('create', \App\Models\Customer::class)
                <a href="{{ route('customers.create') }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Nuevo cliente
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg flex flex-col md:flex-row gap-md md:items-center md:justify-between">
        <form method="GET" action="{{ route('customers.index') }}" class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10" placeholder="Buscar por nombre, identificación o correo" type="search">
        </form>
        <form method="GET" action="{{ route('customers.index') }}" class="flex gap-sm">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                <option value="">Todos los estados</option>
                @foreach (\App\Models\Customer::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($finalConsumer)
                <a href="{{ route('customers.show', $finalConsumer) }}" class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md whitespace-nowrap">Usar consumidor final</a>
            @endif
        </form>
    </div>

    <x-ui.data-table :table="[
        'title' => 'Clientes',
        'headers' => ['Cliente', 'Identificación', 'Ciudad', 'Correo', 'Estado'],
        'hideViewAll' => true,
        'emptyMessage' => 'No hay clientes registrados.',
        'paginator' => $customers,
        'rows' => $customers->map(fn ($customer) => [
            'cells' => [
                $customer->name,
                $customer->identification_type.' '.$customer->identification_number,
                $customer->city ?? '-',
                $customer->email ?? '-',
                $customer->status === 'active' ? 'Activo' : 'Inactivo',
            ],
            'actions' => array_filter([
                ['label' => 'Ver', 'icon' => 'visibility', 'href' => route('customers.show', $customer), 'method' => 'GET'],
                auth()->user()->can('update', $customer) ? ['label' => 'Editar', 'icon' => 'edit', 'href' => route('customers.edit', $customer), 'method' => 'GET'] : null,
            ]),
        ])->all(),
    ]" />
</x-layouts.app>
