<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA Meta Tags -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#4f46e5">
        <link rel="apple-touch-icon" href="/icon-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="MMQP Demo">

        <script>
            window.deferredPwaPrompt = null;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.deferredPwaPrompt = e;
                window.dispatchEvent(new CustomEvent('pwa-ready'));
            });

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js');
                });
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        @include('components.demo-banner')
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-10 pb-10 sm:pt-0 bg-gradient-to-br from-red-50 via-white to-slate-100 dark:from-slate-900 dark:via-slate-800 dark:to-red-950 px-4">
            <div>
                <a href="/" class="flex flex-col items-center gap-2 group">
                    <img src="{{ asset('favicon.ico') }}" alt="MMQP Logo" class="w-24 h-24 object-contain drop-shadow-lg group-hover:scale-105 transition-transform duration-300" />
                    <span class="font-extrabold text-3xl tracking-tight text-red-900 dark:text-white transition-colors duration-300">MMQP <span class="font-light text-red-500 dark:text-red-400">HUB</span></span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-6 py-10 bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl shadow-2xl shadow-red-500/10 dark:shadow-red-900/20 overflow-hidden sm:rounded-3xl border border-white dark:border-slate-700">
                {{ $slot }}
            </div>
        </div>
        @include('components.pwa-install-button')
    </body>
</html>
