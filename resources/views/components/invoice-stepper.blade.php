@props(['current' => 1])

@php($steps = \App\Support\PrototypeScreens::invoiceSteps())

<div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-sm">
        @foreach ($steps as $step)
            @php($isActive = $current === $step['number'])
            @php($isDone = $current > $step['number'])
            <a href="{{ route($step['route']) }}" class="flex items-center gap-sm rounded-lg px-md py-sm {{ $isActive ? 'bg-primary text-on-primary' : ($isDone ? 'bg-primary-fixed text-primary' : 'bg-surface text-on-surface-variant') }}">
                <span class="w-7 h-7 rounded-full flex items-center justify-center font-label-sm text-label-sm {{ $isActive ? 'bg-on-primary text-primary' : 'bg-surface-container-lowest text-primary' }}">
                    {{ $isDone ? '✓' : $step['number'] }}
                </span>
                <span class="font-label-md text-label-md">{{ $step['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
