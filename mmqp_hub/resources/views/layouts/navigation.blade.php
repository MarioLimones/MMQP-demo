<nav x-data="{
    open: false,
    mobileNavigation: window.matchMedia('(max-width: 1023px)').matches,
    isDark: document.documentElement.classList.contains('dark'),
    toggleTheme() {
        this.isDark = !document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark', this.isDark);
        localStorage.theme = this.isDark ? 'dark' : 'light';
    }
}" @resize.window="mobileNavigation = window.matchMedia('(max-width: 1023px)').matches" class="app-navigation bg-white dark:bg-slate-800 sm:w-64 sm:flex-shrink-0 sm:border-r border-gray-200 dark:border-slate-700 transition-colors duration-300 flex flex-col sm:h-screen sm:sticky sm:top-0 z-40">
    <!-- Topbar Mobile -->
    <div class="app-mobile-topbar sm:hidden border-b border-gray-200 dark:border-slate-700 px-4 flex justify-between h-16 items-center">
        <!-- Logo Mobile -->
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <img src="{{ asset('favicon.ico') }}" alt="MMQP Logo" class="w-8 h-8 object-contain" />
            <span class="font-extrabold text-lg text-slate-900 dark:text-white">MMQP <span class="font-light text-slate-500">HUB</span></span>
        </a>
        
        <!-- Hamburger -->
        <div class="flex items-center gap-2">
            @if(request()->routeIs('dashboard.calendario_global'))
                @include('layouts.navigation-account', ['compact' => true])
            @endif
            <button type="button" @click="open = ! open" :aria-expanded="open" aria-label="Abrir o cerrar menú" class="text-gray-500 dark:text-gray-400 p-2 min-w-[44px] min-h-[44px]">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Sidebar Content (Desktop + Mobile expanded) -->
    @if(request()->routeIs('dashboard.calendario_global'))
        <div class="app-navigation-reveal sm:flex sm:flex-1 sm:min-h-0"
            :class="{ 'calendar-menu-open': open }"
            :inert="mobileNavigation && !open" :aria-hidden="mobileNavigation && !open ? 'true' : null">
    @endif
    <div
        @if(!request()->routeIs('dashboard.calendario_global'))
            :class="{'block': open, 'hidden': ! open}"
        @endif
        class="app-navigation-content sm:flex flex-col flex-1 h-full sm:h-auto overflow-y-auto overflow-x-hidden">
        
        <!-- Logo Desktop -->
        <div class="app-navigation-brand hidden sm:flex items-center justify-center h-20 border-b border-gray-200 dark:border-slate-700 shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group px-4">
                <img src="{{ asset('favicon.ico') }}" alt="MMQP Logo" class="w-10 h-10 object-contain group-hover:scale-105 transition-transform duration-300 drop-shadow-sm" />
                <span class="font-extrabold text-xl tracking-tight text-slate-900 dark:text-white transition-colors duration-300">MMQP <span class="font-light text-slate-500 dark:text-slate-400">HUB</span></span>
            </a>
        </div>

        <!-- Navigation Links -->
        <div class="app-navigation-links flex-1 py-6 px-4 space-y-1.5 overflow-y-auto">
            <!-- Mi Portal -->
            @if(strtolower(auth()->user()->rol) !== 'oficina')
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('dashboard') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span>{{ __('Mi Portal') }}</span>
                </a>

                <!-- Calendario -->
                <a href="{{ route('dashboard.calendario') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('dashboard.calendario') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>{{ __('Calendario') }}</span>
                </a>

                <!-- Calendario Global -->
                <a href="{{ route('dashboard.calendario_global') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('dashboard.calendario_global') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>{{ __('Calendario Global') }}</span>
                </a>
            @endif

            @if(auth()->user()->es_admin)
                <div class="app-navigation-section pt-6 pb-2">
                    <p class="px-4 text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                        Administración
                    </p>
                </div>


                <!-- Arqueo -->
                <a href="{{ route('admin.arqueo') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('admin.arqueo') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    <span>{{ __('Arqueo') }}</span>
                </a>

                <!-- Consumos -->
                <a href="{{ route('admin.consumos') }}" @if(request()->routeIs('admin.consumos*')) aria-current="page" @endif class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('admin.consumos*') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <svg aria-hidden="true" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 7 9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7M12 11v10" /></svg>
                    <span>{{ __('Consumos') }}</span>
                </a>

                <!-- Cuadre de Caja Accordion -->
                @php
                    $isCuadreActive = request()->routeIs('admin.cuadre*');
                @endphp
                <div x-data="{ openCuadre: {{ $isCuadreActive ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="openCuadre = !openCuadre" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition-colors {{ $isCuadreActive ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-green-700' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>{{ __('Cuadre de Caja') }}</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': openCuadre}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div x-show="openCuadre" x-collapse x-cloak class="app-navigation-submenu pl-11 pr-2 space-y-1">
                        <!-- Cuadre de Caja -->
                        <a href="{{ route('admin.cuadre') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.cuadre') ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-green-700' }}">
                            {{ __('Cuadre de Caja') }}
                        </a>
                        
                        <!-- Historial -->
                        <a href="{{ route('admin.cuadre.historial') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.cuadre.historial') ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-green-700' }}">
                            {{ __('Historial') }}
                        </a>
                    </div>
                </div>

                <!-- Solicitudes -->
                @php $pendientesNav = \App\Models\SolicitudAusencia::whereHas('user', fn ($query) => $query->employees())->where('estado', 'pendiente')->count(); @endphp
                <a href="{{ route('admin.solicitudes') }}" class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('admin.solicitudes') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        <span>{{ __('Solicitudes') }}</span>
                    </div>
                    @if($pendientesNav > 0)
                        <span class="bg-amber-500 text-white font-bold px-2 py-0.5 rounded-full text-[10px] shadow-sm">{{ $pendientesNav }}</span>
                    @endif
                </a>

                <!-- Gestor de Vacaciones -->
                <a href="{{ route('admin.vacaciones') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-colors {{ request()->routeIs('admin.vacaciones') ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>{{ __('Gestor de Vacaciones') }}</span>
                </a>

                <!-- Empleados Accordion -->
                @php
                    $isEmpleadosActive = request()->routeIs('admin.empleados*') || request()->routeIs('admin.fichajes*') || request()->routeIs('admin.registro-jornada*');
                @endphp
                <div x-data="{ openEmp: {{ $isEmpleadosActive ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="openEmp = !openEmp" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition-colors {{ $isEmpleadosActive ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <span>{{ __('Empleados') }}</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': openEmp}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div x-show="openEmp" x-collapse x-cloak class="app-navigation-submenu pl-11 pr-2 space-y-1">
                        <!-- Maestro de empleados -->
                        <a href="{{ route('admin.empleados') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.empleados') ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                            {{ __('Maestro de empleados') }}
                        </a>
                        
                        <!-- Empleados Activos -->
                        <a href="{{ route('admin.empleados.activos') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.empleados.activos') ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                            {{ __('Empleados') }}
                        </a>

                        <!-- Control de Asistencia -->
                        <a href="{{ route('admin.fichajes.index') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.fichajes*') ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                            {{ __('Control de Asistencia') }}
                        </a>
                        
                        <!-- Registro de Jornada -->
                        <a href="{{ route('admin.registro-jornada.index') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.registro-jornada*') ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                            {{ __('Registro de Jornada') }}
                        </a>
                    </div>
                </div>

                <!-- Horarios Accordion -->
                @php
                    $isHorariosActive = request()->routeIs('admin.horarios*');
                @endphp
                <div x-data="{ openHor: {{ $isHorariosActive ? 'true' : 'false' }} }" class="space-y-1">
                    <button @click="openHor = !openHor" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition-colors {{ $isHorariosActive ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span>{{ __('Horarios') }}</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': openHor}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div x-show="openHor" x-collapse x-cloak class="app-navigation-submenu pl-11 pr-2 space-y-1">
                        <!-- Diseñador -->
                        <a href="{{ route('admin.horarios') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.horarios') ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                            {{ __('Diseñador') }}
                        </a>
                        
                        <!-- Historial -->
                        <a href="{{ route('admin.horarios.historial') }}" class="block px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('admin.horarios.historial') ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-red-700' }}">
                            {{ __('Historial') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Bottom User / Settings -->
        @include('layouts.navigation-account', ['compact' => false])
    </div>
    @if(request()->routeIs('dashboard.calendario_global'))
        </div>
    @endif
</nav>
