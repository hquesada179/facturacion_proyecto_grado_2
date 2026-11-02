@php
    $notificationItems = app(\App\Services\Notifications\NotificationCenterService::class)->forUser(auth()->user());
@endphp

<header class="hidden md:flex justify-between items-center w-full px-lg py-md h-16 bg-surface/95 backdrop-blur z-30 sticky top-0 border-b border-outline-variant/30 shadow-[0_1px_2px_rgba(11,28,48,0.03)]">
    <div class="flex-1 flex items-center">
        <div class="relative w-96">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
            <input class="w-full pl-10 pr-4 py-2 bg-surface-container-lowest border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10 transition-all" placeholder="Buscar documentos, clientes..." type="search">
        </div>
    </div>

    <div class="flex items-center gap-md">
        <x-notification-center :items="$notificationItems" :user-id="auth()->id()" />
        <button
            type="button"
            class="icon-btn p-2 rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-container transition-all"
            data-theme-toggle
            aria-pressed="false"
            aria-label="Cambiar a modo oscuro"
        >
            <span class="material-symbols-outlined" data-theme-icon>dark_mode</span>
        </button>
        <button class="icon-btn p-2 rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-container transition-all" type="button" aria-label="Ayuda">
            <span class="material-symbols-outlined">help_outline</span>
        </button>
    </div>
</header>

<header class="md:hidden sticky top-0 z-40 bg-surface-container-lowest border-b border-outline-variant/40 px-margin-mobile py-md flex items-center justify-between gap-sm">
    <button
        type="button"
        class="icon-btn p-xs rounded-lg text-on-surface-variant hover:text-primary hover:bg-surface-container shrink-0"
        data-sidebar-toggle
        aria-label="Abrir menú de navegación"
    >
        <span class="material-symbols-outlined">menu</span>
    </button>
    <a href="{{ route('dashboard') }}" class="flex-1 flex items-center justify-center gap-sm text-primary min-w-0">
        <img src="{{ asset('branding/fiscora-icon.png') }}" alt="Fiscora" class="h-7 w-7 object-contain">
        <span class="font-title-lg text-title-lg truncate">Fiscora</span>
    </a>
    <x-notification-center :items="$notificationItems" :user-id="auth()->id()" />
    <button
        type="button"
        class="icon-btn p-xs rounded-lg text-on-surface-variant hover:text-primary hover:bg-surface-container shrink-0"
        data-theme-toggle
        aria-pressed="false"
        aria-label="Cambiar a modo oscuro"
    >
        <span class="material-symbols-outlined" data-theme-icon>dark_mode</span>
    </button>
    <a href="{{ route('assistant.index') }}" class="icon-btn p-sm rounded-full ai-gradient text-on-primary shrink-0" aria-label="Asistente IA">
        <span class="material-symbols-outlined">auto_awesome</span>
    </a>
</header>
