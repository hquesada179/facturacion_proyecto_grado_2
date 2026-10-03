<div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 mb-lg">
    <div class="flex flex-wrap p-sm gap-sm">
        @foreach (\App\Support\PrototypeScreens::settingsTabs() as $tab)
            @php($isActive = request()->routeIs($tab['route']) || request()->routeIs($tab['route'].'.*'))
            <a href="{{ route($tab['route']) }}" class="px-md py-sm rounded-lg font-label-md text-label-md {{ $isActive ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' }}">{{ $tab['label'] }}</a>
        @endforeach
    </div>
</div>
