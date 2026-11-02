@props(['active' => 'dashboard'])

@php($items = collect(\App\Support\PrototypeScreens::navigation())->filter(fn ($item) => ! isset($item['permission']) || auth()->user()->can($item['permission']))->all())

{{-- UI/UX 2.0-A: same nav tree powers desktop (always visible, fixed)
     and mobile (off-canvas drawer toggled by [data-sidebar-open] on
     <html>, see app.js) — one source of truth for routes/labels/icons,
     a pure CSS transform decides whether it's on-screen. --}}
<div
    class="sidebar-backdrop fixed inset-0 z-[45] bg-[#0b1c30]/50 md:hidden"
    data-sidebar-backdrop
></div>

<nav
    class="sidebar-drawer fixed left-0 top-0 h-screen w-72 md:w-64 flex flex-col z-50 sidebar-surface shadow-xl md:shadow-sm"
    aria-label="Navegación principal"
>
    <div class="p-lg flex items-center gap-sm">
        <img src="{{ asset('branding/fiscora-icon.png') }}" alt="Fiscora" class="h-9 w-9 shrink-0 object-contain">
        <div class="min-w-0 flex-1">
            <h1 class="font-headline-md text-headline-md text-inverse-on-surface leading-tight">Fiscora</h1>
            <p class="font-label-sm text-label-sm text-inverse-on-surface/60 truncate">Plataforma de Facturación</p>
        </div>
        <button
            type="button"
            class="md:hidden icon-btn p-xs rounded-full text-inverse-on-surface/70 hover:text-inverse-on-surface hover:bg-white/10"
            data-sidebar-backdrop
            aria-label="Cerrar menú"
        >
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    @can('manage-invoicing')
        <div class="px-md mb-md">
            <a
                href="{{ route('invoices.create.customer') }}"
                class="icon-btn w-full bg-inverse-on-surface text-primary font-label-md text-label-md py-sm px-md rounded-lg flex items-center justify-center gap-sm shadow-hover"
                @if (request()->routeIs('invoices.draft.*'))
                    onclick="return confirm('Ya tienes una factura en progreso en este asistente. Si creas una nueva, esta quedará como un borrador aparte y dejarás de ver la factura actual. ¿Deseas continuar?');"
                @endif
            >
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
                        'sidebar-link flex items-center gap-md px-md py-sm rounded-lg font-label-md text-label-md',
                        'sidebar-link-active' => $isActive,
                    ])
                >
                    <span class="material-symbols-outlined" @if($isActive) data-fill="true" @endif>{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="p-md border-t border-white/10 flex items-center gap-xs">
        <a href="{{ route('profile.show') }}" class="flex-1 min-w-0 flex items-center gap-sm rounded-lg p-xs hover:bg-white/8 transition-colors">
            <div class="w-10 h-10 rounded-full bg-white/12 text-inverse-on-surface flex items-center justify-center font-label-md text-label-md shrink-0">{{ auth()->user()->name ? collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') : '' }}</div>
            <div class="min-w-0">
                <p class="font-label-md text-label-md text-inverse-on-surface font-semibold truncate">{{ auth()->user()->name }}</p>
                <p class="font-label-sm text-label-sm text-inverse-on-surface/60 truncate">{{ auth()->user()->email }}</p>
                <p class="font-label-sm text-label-sm text-inverse-primary truncate">{{ auth()->user()->role->label() }}</p>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="icon-btn p-xs rounded-lg text-inverse-on-surface/60 hover:text-[#ff9b9b] hover:bg-white/8 transition-colors" aria-label="Cerrar sesión">
                <span class="material-symbols-outlined">logout</span>
            </button>
        </form>
    </div>
</nav>
