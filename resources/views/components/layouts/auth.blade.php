@props(['title' => null])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="application-name" content="{{ config('app.name') }}">
    <title>{{ $title ? $title.' | '.config('app.name') : config('app.name') }}</title>
    <script>
        // Reuses the exact same mechanism as layouts/app.blade.php — same
        // storage key, same system-preference fallback — so the auth
        // screens respect whatever theme the user already chose instead
        // of always forcing light mode. No second dark-mode system.
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
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16x16.png') }}">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('branding/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-on-surface antialiased font-body-md text-body-md">
    {{ $slot }}
</body>
</html>
