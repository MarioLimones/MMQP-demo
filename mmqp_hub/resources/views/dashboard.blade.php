<x-app-layout>
    <div class="py-8 relative z-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-2xl p-6 md:p-8 flex flex-col md:flex-row flex-wrap justify-between items-start md:items-center gap-4 transition-colors duration-300 relative">
                
                <div class="relative z-10">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white">¡Hola, {{ explode(' ', auth()->user()->nombre)[0] }}! 👋</h3>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Este es tu resumen de turnos y horarios asignados.</p>
                </div>
                <div class="flex flex-col sm:flex-row flex-wrap min-w-0 max-w-full gap-3 mt-4 md:mt-0 w-full md:w-auto relative z-10">
                    <a href="{{ route('dashboard.calendario') }}" class="w-full sm:w-auto justify-center inline-flex items-center px-5 py-2.5 bg-white dark:bg-slate-700 text-red-600 dark:text-red-300 border border-red-100 dark:border-slate-600 rounded-xl font-bold hover:bg-red-50 hover:text-red-700 dark:hover:bg-slate-600 dark:hover:text-red-200 transition-colors shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Ver Calendario
                    </a>
                    <a href="{{ route('dashboard.calendario_global') }}" class="w-full sm:w-auto justify-center inline-flex items-center px-5 py-2.5 bg-white dark:bg-slate-700 text-teal-700 dark:text-teal-400 border border-teal-100 dark:border-slate-600 rounded-xl font-bold hover:bg-teal-50 dark:hover:bg-slate-600 dark:hover:text-teal-300 transition-colors shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Calendario Global
                    </a>
                    @if(auth()->user()->es_admin)
                        <a href="{{ route('admin.horarios') }}" class="w-full sm:w-auto justify-center inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-red-600 to-purple-600 border border-transparent rounded-xl font-bold text-white shadow-lg shadow-red-500/30 hover:scale-105 hover:-translate-y-0.5 transition-all duration-300">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Gestor de Horarios
                        </a>
                    @endif
                </div>
            </div>

            <!-- Próximo Turno -->
            @if($proximoTurno)
                @php
                    try {
                        $proxFechaObj = \Carbon\Carbon::createFromFormat(str_contains($proximoTurno['fecha'], '/') ? 'd/m/Y' : 'Y-m-d', trim($proximoTurno['fecha']));
                    } catch (\Exception $e) {
                        $proxFechaObj = \Carbon\Carbon::parse(trim($proximoTurno['fecha']));
                    }
                    $proxFechaObj->locale('es');
                @endphp
                <div class="bg-gradient-to-r from-red-600 to-purple-600 overflow-hidden shadow-xl shadow-red-500/20 sm:rounded-3xl p-8 relative text-white">
                    <!-- Decoración fondo -->
                    <svg class="absolute top-0 right-0 w-64 h-64 text-white opacity-5 -mt-10 -mr-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    
                    <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="w-full md:w-auto text-center md:text-left">
                            <span class="bg-white/20 backdrop-blur-md text-red-50 font-bold px-4 py-1.5 rounded-full text-xs uppercase tracking-widest mb-4 inline-block border border-white/10 shadow-sm">
                                Próximo Turno
                            </span>
                            <h2 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-2 capitalize">
                                {{ $proxFechaObj->isoFormat('dddd D [de] MMMM') }}
                            </h2>
                            <p class="text-red-100 text-lg flex items-center justify-center md:justify-start gap-2">
                                <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                De <span class="font-bold text-white">{{ $proximoTurno['hora_inicio'] }}</span> a <span class="font-bold text-white">{{ $proximoTurno['hora_fin'] }}</span>
                            </p>
                        </div>
                        <div class="text-center md:text-right shrink-0">
                            <div class="text-5xl md:text-6xl font-black opacity-90 drop-shadow-md">{{ $proximoTurno['duracion'] }}h</div>
                            <div class="bg-white/10 px-3 py-1 rounded-lg inline-block text-red-100 text-xs font-bold uppercase tracking-widest mt-2 border border-white/10">
                                {{ $proximoTurno['rol'] }}
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/50 overflow-hidden shadow-sm sm:rounded-3xl p-10 text-center transition-colors duration-300">
                    <div class="text-5xl mb-4">🎉</div>
                    <h3 class="text-2xl font-bold text-emerald-800 dark:text-emerald-400 mb-2">¡Sin turnos programados!</h3>
                    <p class="text-emerald-700 dark:text-emerald-400 max-w-lg mx-auto">No tienes turnos pendientes en el horario actual. ¡Disfruta de tu tiempo libre!</p>
                </div>
            @endif

            <!-- Cuadrante de la semana -->
            @if(count($misTurnos) > 0)
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-3xl transition-colors duration-300">
                    <div class="p-6 md:p-8 border-b border-slate-100 dark:border-slate-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white">Mis Turnos ({{ count($misTurnos) }})</h3>
                            @if($ultimoHorario)
                                @php
                                    \Carbon\Carbon::setLocale('es');
                                    // Parse year and week from "2026-W32" format
                                    $year = substr($ultimoHorario->semana_id, 0, 4);
                                    $week = substr($ultimoHorario->semana_id, 6);
                                    $date = \Carbon\Carbon::now()->setISODate($year, $week);
                                    $f_inicio = $date->copy()->startOfWeek()->isoFormat('D MMM');
                                    $f_fin = $date->copy()->endOfWeek()->isoFormat('D MMM YYYY');
                                @endphp
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">
                                    Semana del {{ $f_inicio }} al {{ $f_fin }}
                                </p>
                            @endif
                        </div>
                    </div>
                    
                    <div class="p-6 md:p-8 bg-slate-50/50 dark:bg-slate-800/50">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                            @foreach($turnosAgrupados as $fecha => $turnosDelDia)
                                @php
                                    $fechaObj = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha);
                                    $fechaObj->locale('es');
                                    $esHoy = $fechaObj->isToday();
                                @endphp
                                <div class="bg-white dark:bg-slate-700 rounded-2xl p-6 border {{ $esHoy ? 'border-red-400 dark:border-red-500 shadow-md ring-2 ring-red-500/20' : 'border-slate-200 dark:border-slate-600 shadow-sm hover:border-slate-300 dark:hover:border-slate-500 hover:shadow-md' }} flex flex-col relative transition-all duration-300">
                                    @if($esHoy)
                                        <div class="absolute -top-3 -right-3">
                                            <span class="bg-red-500 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest shadow-md flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                                Hoy
                                            </span>
                                        </div>
                                    @endif
                                    
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <span class="text-xs font-bold text-red-500 dark:text-red-400 uppercase tracking-widest block mb-0.5">{{ $fechaObj->isoFormat('dddd') }}</span>
                                            <span class="text-2xl font-black text-slate-800 dark:text-white">{{ $fechaObj->format('d/m') }}</span>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-600 flex items-center justify-center text-slate-400 dark:text-slate-300 border border-slate-100 dark:border-slate-500">
                                            @if(count($turnosDelDia) > 1)
                                                <span class="text-sm font-bold text-red-600 dark:text-red-400">x{{ count($turnosDelDia) }}</span>
                                            @else
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="mt-auto pt-4 border-t border-slate-100 dark:border-slate-600 flex flex-col gap-3">
                                        @foreach($turnosDelDia as $turno)
                                            <div class="flex justify-between items-end">
                                                <div>
                                                    <div class="text-[10px] text-slate-400 dark:text-slate-400 uppercase tracking-widest font-bold mb-1">Horario</div>
                                                    <div class="text-lg font-bold text-slate-800 dark:text-slate-100 leading-none flex items-center gap-1.5">
                                                        {{ $turno['hora_inicio'] }} 
                                                        <span class="text-slate-300 dark:text-slate-500 font-normal">→</span> 
                                                        {{ $turno['hora_fin'] }}
                                                    </div>
                                                </div>
                                                <div class="bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300 border border-red-100/50 dark:border-red-500/30 font-black px-2 py-1 rounded-lg text-sm shadow-sm">
                                                    {{ $turno['duracion'] }}h
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
