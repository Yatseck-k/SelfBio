<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#030712">
        <meta name="color-scheme" content="dark light">
        <script>try{document.documentElement.dataset.theme=localStorage.getItem('selfbio-theme')|| (matchMedia('(prefers-color-scheme: light)').matches?'light':'dark')}catch(e){document.documentElement.dataset.theme='dark'}</script>
        @include('partials.seo')
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="preload" href="/fonts/golos-text-400-800-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/lora-500-700-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @inertia
        @if ($seo['noscript'] !== '')
            <noscript>{!! $seo['noscript'] !!}</noscript>
        @endif
    </body>
</html>
