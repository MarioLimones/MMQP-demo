<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MMQP Demo | Gestión de equipos</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Tailwind / Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a; /* Slate 900 */
            color: #f8fafc;
            overflow-x: hidden;
        }
        
        /* Glowing background effects */
        .glow-blob {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(99,102,241,0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            filter: blur(40px);
        }
        
        .glow-blob-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(236,72,153,0.1) 0%, rgba(236,72,153,0) 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            filter: blur(40px);
        }

        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .text-gradient {
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="antialiased min-h-screen relative flex flex-col selection:bg-red-500/30">

    <!-- Ambient Backgrounds -->
    <div class="glow-blob top-[-100px] left-[-100px] animate-pulse" style="animation-duration: 8s;"></div>
    <div class="glow-blob-2 bottom-[10%] right-[-50px] animate-pulse" style="animation-duration: 12s;"></div>

    <!-- Navigation -->
    <nav class="w-full absolute top-0 left-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-6 flex justify-between items-center">
            <div class="flex items-center gap-3 group cursor-pointer">
                <img src="{{ asset('favicon.ico') }}" alt="MMQP Logo" class="w-10 h-10 object-contain drop-shadow-lg shadow-red-500/30 group-hover:scale-105 transition-transform duration-300" />
                <span class="font-extrabold text-xl tracking-tight text-white group-hover:text-red-200 transition-colors">MMQP <span class="font-light text-slate-400">HUB</span></span>
            </div>

            <div>
                @if (Route::has('login'))
                    <div class="flex gap-4 items-center">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="px-6 py-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white font-semibold text-sm backdrop-blur-sm border border-white/10 transition-all duration-300 hover:scale-105 hover:shadow-lg">
                                Ir al Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="px-6 py-2.5 rounded-full bg-gradient-to-r from-red-600 to-purple-600 hover:from-red-500 hover:to-purple-500 text-white font-semibold text-sm shadow-lg shadow-red-500/25 transition-all duration-300 hover:scale-105 hover:-translate-y-0.5">
                                Probar la demo
                            </a>
                        @endauth
                    </div>
                @endif
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center pt-24 pb-12 px-6">
        <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            
            <!-- Left side: Hero Text -->
            <div class="space-y-8 relative z-10 text-center lg:text-left">
                <div class="inline-block px-4 py-1.5 rounded-full glass-card border border-red-500/30 text-red-300 text-xs font-bold tracking-wider uppercase mb-2 animate-fade-in-up">
                    Demo interactiva · Datos ficticios
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-7xl font-extrabold leading-[1.1] tracking-tight">
                    Gestión de turnos <br/>
                    <span class="text-gradient">Inteligente</span>
                </h1>
                <p class="text-base sm:text-lg text-slate-400 max-w-lg mx-auto lg:mx-0 leading-relaxed">
                    Explora MMQP con perfiles, horarios y calendarios inventados. MMQP HUB centraliza los horarios del equipo, automatiza la creación de cuadrantes con Inteligencia Artificial y permite a los empleados consultar sus turnos al instante.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start pt-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-6 py-3 sm:px-8 sm:py-4 rounded-2xl bg-gradient-to-r from-red-600 to-purple-600 hover:from-red-500 hover:to-purple-500 text-white font-bold text-base sm:text-lg shadow-xl shadow-red-500/30 transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] flex items-center justify-center gap-2">
                            <span>Entrar a mi portal</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-6 py-3 sm:px-8 sm:py-4 rounded-2xl bg-gradient-to-r from-red-600 to-purple-600 hover:from-red-500 hover:to-purple-500 text-white font-bold text-base sm:text-lg shadow-xl shadow-red-500/30 transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] flex items-center justify-center gap-2">
                            <span>Acceder a mi cuenta</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                        </a>
                    @endauth
                </div>
                
                <div class="pt-8 flex items-center justify-center lg:justify-start gap-8 text-slate-500">
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-slate-300">100%</span>
                        <span class="text-xs font-medium uppercase tracking-wider">Transparente</span>
                    </div>
                    <div class="w-px h-10 bg-slate-800"></div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-slate-300">24/7</span>
                        <span class="text-xs font-medium uppercase tracking-wider">Disponible</span>
                    </div>
                </div>
            </div>

            <!-- Right side: Feature Cards -->
            <div class="relative z-10 grid grid-cols-1 sm:grid-cols-2 gap-6 w-full mt-8 lg:mt-0 perspective-1000">
                
                <!-- Card 1 -->
                <div class="glass-card p-6 rounded-3xl hover:-translate-y-2 hover:shadow-red-500/10 hover:shadow-2xl transition-all duration-500 group">
                    <div class="w-12 h-12 rounded-2xl bg-red-500/20 text-red-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-200 mb-3">Para Empleados</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Consulta tus turnos de la semana al instante desde tu móvil. Pide días libres o vacaciones y conoce a tus compañeros de turno.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="glass-card p-6 rounded-3xl hover:-translate-y-2 hover:shadow-purple-500/10 hover:shadow-2xl transition-all duration-500 group">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/20 text-purple-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-200 mb-3">Para Managers</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Genera horarios justos y legales automáticamente en segundos con el algoritmo de optimización matemática avanzado.
                    </p>
                </div>

                <!-- Card 3 (Full width) -->
                <div class="glass-card p-6 rounded-3xl sm:col-span-2 hover:-translate-y-2 hover:shadow-sky-500/10 hover:shadow-2xl transition-all duration-500 group">
                    <div class="flex flex-col sm:flex-row gap-6 items-center">
                        <div class="w-12 h-12 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0 group-hover:rotate-12 transition-transform duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-200 mb-2 text-center sm:text-left">Seguro y Privado</h3>
                            <p class="text-sm text-slate-400 leading-relaxed text-center sm:text-left">
                                Accede a tu portal con tu usuario y contraseña. Solo tú puedes ver tus datos, contratos y disponibilidad, manteniéndolo siempre confidencial.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-white/5 py-8 mt-auto z-10 relative">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-sm text-slate-500 font-medium">
                &copy; {{ date('Y') }} MMQP HUB. Todos los derechos reservados.
            </p>
            <div class="flex items-center gap-2 text-slate-600 text-sm">
                <span>Construido con</span>
                <svg class="w-4 h-4 text-rose-500/70" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg>
                <span>para el equipo de MMQP</span>
            </div>
        </div>
    </footer>

</body>
</html>
