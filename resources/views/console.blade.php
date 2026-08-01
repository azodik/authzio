<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Authzio Console</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <script>
        (function () {
            try {
                var serverTheme = @json($themePreference ?? null);
                var theme = serverTheme || localStorage.getItem('authzio-theme') || 'system';
                if (serverTheme) {
                    localStorage.setItem('authzio-theme', serverTheme);
                }
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var dark = theme === 'dark' || (theme === 'system' && prefersDark);
                var root = document.documentElement;
                root.classList.toggle('dark', dark);
                root.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
    @fonts
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/console/main.tsx'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <script>
        window.__AUTHZIO__ = @json($buildInfo);
    </script>
    <div id="console-root"></div>
</body>
</html>
