<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            try {
                var saved = localStorage.getItem('killdoku-theme');
                var dark = saved !== 'light';
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            } catch (e) {}
        </script>
        <title inertia>{{ config('app.name', 'Whodoku') }}</title>
        <link rel="preload" href="/fonts/golos-text-400-800-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/lora-500-700-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
        @vite('resources/whodoku/js/app.ts')
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
