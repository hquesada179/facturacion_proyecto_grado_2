@props([
    'title' => 'FacturaPro Col',
    'active' => 'dashboard',
    'assistant' => true,
    'wide' => false,
])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | FacturaPro Col</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface antialiased font-body-md text-body-md">
    <div class="min-h-screen">
        <x-sidebar :active="$active" />

        <div class="min-h-screen md:ml-64 flex flex-col">
            <x-topbar />

            <main class="flex-1 w-full mx-auto p-margin-mobile md:p-margin-desktop {{ $wide ? 'max-w-[1440px]' : 'max-w-[1280px]' }}">
                @if (session('status'))
                    <div class="mb-lg bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-lg bg-[#fde8e8] border border-[#9b1c1c]/30 text-[#9b1c1c] rounded-lg p-md font-body-sm text-body-sm">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-lg bg-[#fde8e8] border border-[#9b1c1c]/30 text-[#9b1c1c] rounded-lg p-md font-body-sm text-body-sm">
                        <ul class="list-disc list-inside space-y-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($assistant)
                    <div class="flex flex-col xl:flex-row gap-lg items-start">
                        <section class="w-full min-w-0 flex-1">
                            {{ $slot }}
                        </section>

                        <aside class="w-full xl:w-80 shrink-0">
                            <x-assistant-panel />
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
