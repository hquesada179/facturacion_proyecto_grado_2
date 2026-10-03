<x-layouts.app title="Nuevo cliente" active="customers">
    <x-page-header eyebrow="Clientes" title="Nuevo cliente" description="Registra un cliente real para la facturación simulada." />

    <form class="space-y-lg" method="POST" action="{{ route('customers.store') }}">
        @include('customers._form', ['formMethod' => 'POST'])
    </form>
</x-layouts.app>
