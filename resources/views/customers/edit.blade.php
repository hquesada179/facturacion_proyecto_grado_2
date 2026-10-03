<x-layouts.app title="Editar cliente" active="customers">
    <x-page-header eyebrow="Clientes" title="Editar cliente" description="Actualiza los datos del cliente." />

    <form class="space-y-lg" method="POST" action="{{ route('customers.update', $customer) }}">
        @include('customers._form', ['formMethod' => 'PUT'])
    </form>
</x-layouts.app>
