<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-pub-key" content="{{ config('webpush.vapid.public_key') }}">

        <title>MMQP Demo</title>

        <!-- Keep Alpine panels hidden from the first paint until initialization. -->
        <style>[x-cloak] { display: none !important; }</style>

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
                    navigator.serviceWorker.register('/sw.js').then(registration => {
                        console.log('SW registered: ', registration);
                        window.swRegistration = registration;
                    }).catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
                });
            }

            async function subscribeToPushNotifications() { return false; }
            async function unsubscribeFromPushNotifications() { return false; }
            async function checkPushSubscriptionStatus() { return false; }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Dark Mode Init -->
        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        </script>
    </head>
    <body class="{{ request()->routeIs('dashboard.calendario_global') ? 'global-calendar-page' : '' }} font-sans antialiased text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
        <div class="app-shell flex flex-col sm:flex-row min-h-screen bg-gray-100 dark:bg-slate-900 transition-colors duration-300 pb-16 sm:pb-0">
            @include('layouts.navigation')

            <!-- Contenedor Principal a la derecha -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                @include('components.demo-banner')
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white dark:bg-slate-800 shadow transition-colors duration-300 relative z-10">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-slate-900">
                    @include('components.demo-help')
                    {{ $slot }}
                </main>
            </div>
        </div>
        
        @if(auth()->check() && strtolower(auth()->user()->rol) !== 'oficina')
        <!-- Mobile Bottom Navbar -->
        <nav class="app-bottom-navigation sm:hidden fixed bottom-0 w-full bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 z-50 transition-colors duration-300">
            <div class="flex justify-around items-center h-16 px-2">
                <!-- Home / Dashboard -->
                <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center w-full h-full space-y-1 group {{ request()->routeIs('dashboard') ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400 hover:text-red-500 dark:hover:text-red-300' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <span class="text-[10px] font-medium tracking-wide">Inicio</span>
                </a>
                
                <!-- Mi Calendario -->
                <a href="{{ route('dashboard.calendario') }}" class="flex flex-col items-center justify-center w-full h-full space-y-1 group {{ request()->routeIs('dashboard.calendario') ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400 hover:text-red-500 dark:hover:text-red-300' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-[10px] font-medium tracking-wide">Mis Turnos</span>
                </a>
                
                <!-- Calendario Global -->
                <a href="{{ route('dashboard.calendario_global') }}" class="flex flex-col items-center justify-center w-full h-full space-y-1 group {{ request()->routeIs('dashboard.calendario_global') ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400 hover:text-red-500 dark:hover:text-red-300' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span class="text-[10px] font-medium tracking-wide">Global</span>
                </a>
                
                <!-- Perfil -->
                <a href="{{ route('profile.edit') }}" class="flex flex-col items-center justify-center w-full h-full space-y-1 group {{ request()->routeIs('profile.edit') ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400 hover:text-red-500 dark:hover:text-red-300' }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span class="text-[10px] font-medium tracking-wide">Perfil</span>
                </a>
            </div>
        </nav>
        @endif
        @include('components.pwa-install-button')
        @stack('scripts')
    </body>
</html>
