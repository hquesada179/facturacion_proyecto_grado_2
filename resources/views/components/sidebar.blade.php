@props(['active' => 'dashboard'])

@php($items = \App\Support\PrototypeScreens::navigation())

<nav class="hidden md:flex fixed left-0 top-0 h-screen w-64 flex-col z-40 bg-surface-container-lowest border-r border-outline-variant/50 shadow-sm">
    <div class="p-lg flex items-center gap-sm">
        <span class="material-symbols-outlined text-primary text-[28px]" data-fill="true">corporate_fare</span>
        <div class="min-w-0">
            <h1 class="font-headline-md text-headline-md text-primary leading-tight">FacturaPro Col</h1>
            <p class="font-label-sm text-label-sm text-on-surface-variant">Gestión inteligente</p>
        </div>
    </div>

    <div class="px-md mb-md">
        <a href="{{ route('invoices.create.customer') }}" class="w-full bg-primary text-on-primary font-label-md text-label-md py-sm px-md rounded-lg flex items-center justify-center gap-sm shadow-hover">
            <span class="material-symbols-outlined">add</span>
            Nueva factura
        </a>
    </div>

    <ul class="flex-1 overflow-y-auto px-sm py-sm space-y-xs">
        @foreach ($items as $item)
            @php($isActive = $active === $item['key'])
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'flex items-center gap-md px-md py-sm rounded-lg transition-colors duration-200 font-label-md text-label-md',
                        'text-primary font-bold border-r-4 border-primary bg-surface-container-low' => $isActive,
                        'text-on-surface-variant hover:text-primary hover:bg-surface-container' => ! $isActive,
                    ])
                >
                    <span class="material-symbols-outlined" @if($isActive) data-fill="true" @endif>{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="p-md border-t border-surface-variant">
        <a href="{{ route('profile.show') }}" class="flex items-center gap-sm rounded-lg p-xs hover:bg-surface-container transition-colors">
            <div class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-md text-label-md">AD</div>
            <div class="min-w-0">
                <p class="font-label-md text-label-md text-on-surface font-semibold truncate">Administrador</p>
                <p class="font-label-sm text-label-sm text-on-surface-variant truncate">admin@empresa.com.co</p>
            </div>
        </a>
    </div>
</nav>
