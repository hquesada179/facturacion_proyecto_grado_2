<x-layouts.app title="Editar producto o servicio" active="products">
    <x-page-header eyebrow="Productos y servicios" title="Editar producto o servicio" description="Ajusta datos comerciales e impuestos." />

    <form class="space-y-lg" method="POST" action="{{ route('products.update', $product) }}">
        @include('products._form', ['formMethod' => 'PUT'])
    </form>
</x-layouts.app>
