@props(['active' => 'dashboard'])

@php($items = collect(\App\Support\PrototypeScreens::navigation())->filter(fn ($item) => ! isset($item['permission']) || auth()->user()->can($item['permission']))->all())

<nav class="hidden md:flex fixed left-0 top-0 h-screen w-64 flex-col z-40 bg-surface-container-lowest border-r border-outline-variant/50 shadow-sm">
    <div class="p-lg flex items-center gap-sm">
        <span class="material-symbols-outlined text-primary text-[28px]" data-fill="true">corporate_fare</span>
        <div class="min-w-0">
            <h1 class="font-headline-md text-headline-md text-primary leading-tight">FacturaPro Col</h1>
            <p class="font-label-sm text-label-sm text-on-surface-variant">Gestión inteligente</p>
        </div>
    </div>

    @can('manage-invoicing')
        <div class="px-md mb-md">
            <a href="{{ route('invoices.create.customer') }}" class="w-full bg-primary text-on-primary font-label-md text-label-md py-sm px-md rounded-lg flex items-center justify-center gap-sm shadow-hover">
                <span class="material-symbols-outlined">add</span>
                Nueva factura
            </a>
        </div>
    @endcan

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

    <div class="p-md border-t border-surface-variant flex items-center gap-xs">
        <a href="{{ route('profile.show') }}" class="flex-1 min-w-0 flex items-center gap-sm rounded-lg p-xs hover:bg-surface-container transition-colors">
            <div class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-md text-label-md">{{ auth()->user()->name ? collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') : '' }}</div>
            <div class="min-w-0">
                <p class="font-label-md text-label-md text-on-surface font-semibold truncate">{{ auth()->user()->name }}</p>
                <p class="font-label-sm text-label-sm text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="p-xs rounded-lg text-on-surface-variant hover:text-error hover:bg-surface-container transition-colors" aria-label="Cerrar sesión">
                <span class="material-symbols-outlined">logout</span>
            </button>
        </form>
    </div>
</nav>
