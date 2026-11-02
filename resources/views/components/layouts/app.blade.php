@props([
    'title' => null,
    'active' => 'dashboard',
    'assistant' => true,
    'wide' => false,
    'screen' => null,
    'resourceType' => null,
    'resourceId' => null,
])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="application-name" content="{{ config('app.name') }}">
    <title>{{ $title ? $title.' | '.config('app.name') : config('app.name') }}</title>
    <script>
        // UI/UX 2.0-C — applied before any CSS/JS loads so the page never
        // flashes the wrong theme on reload. Mirrors resources/js/theme.js
        // exactly (same storage key, same system-preference fallback); kept
        // inline and dependency-free on purpose — anything deferred would
        // paint at least one frame too late.
        (function () {
            try {
                var stored = window.localStorage.getItem('fiscora:theme');
                var theme = stored === 'light' || stored === 'dark'
                    ? stored
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>
    <script>
        // UI/UX 3.1 — same reasoning as the theme script above: applied
        // before any CSS/JS loads so a reload never flashes the sidebar
        // expanded for a frame before collapsing. Mirrors
        // resources/js/sidebar.js's own storage key exactly. Desktop/
        // tablet only — mobile always uses the off-canvas drawer
        // ([data-sidebar-open]), a completely separate mechanism this
        // never touches.
        (function () {
            try {
                if (window.localStorage.getItem('fiscora.sidebar.collapsed') === 'true') {
                    document.documentElement.setAttribute('data-sidebar-collapsed', '');
                }
            } catch (error) {
                // Private browsing / storage disabled — sidebar simply
                // starts expanded, same as the documented fallback.
            }
        })();
    </script>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16x16.png') }}">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('branding/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface antialiased font-body-md text-body-md">
    <div class="min-h-screen">
        <x-sidebar :active="$active" />

        <div class="main-column min-h-screen md:ml-64 flex flex-col">
            <x-topbar />

            <main class="flex-1 w-full mx-auto p-margin-mobile md:p-margin-desktop {{ $wide ? 'max-w-[1440px]' : 'max-w-[1280px]' }}">
                @if (session('status'))
                    <x-ui.alert tone="success" class="mb-lg">
                        {{ session('status') }}
                    </x-ui.alert>
                @endif

                @if (session('error'))
                    <x-ui.alert tone="error" class="mb-lg">
                        {{ session('error') }}
                    </x-ui.alert>
                @endif

                @if ($errors->any())
                    <x-ui.alert tone="error" title="Revisa los siguientes datos" class="mb-lg">
                        <ul class="list-disc list-inside space-y-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                @if ($assistant)
                    <div class="flex flex-col xl:flex-row gap-lg items-start">
                        <section class="w-full min-w-0 flex-1">
                            {{ $slot }}
                        </section>

                        <aside class="w-full xl:w-96 shrink-0">
                            <x-assistant-panel
                                :screen="$screen ?? $active"
                                :resource-type="$resourceType"
                                :resource-id="$resourceId"
                            />
                        </aside>
                    </div>
                @else
                    {{ $slot }}
                @endif
            </main>
        </div>
    </div>
</body>
</html>
