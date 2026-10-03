<x-layouts.app title="Nuevo producto o servicio" active="products">
    <x-page-header eyebrow="Productos y servicios" title="Nuevo producto o servicio" description="Define precio, tipo e impuestos asociados." />

    <form class="space-y-lg" method="POST" action="{{ route('products.store') }}">
        @include('products._form', ['formMethod' => 'POST'])
    </form>
</x-layouts.app>
