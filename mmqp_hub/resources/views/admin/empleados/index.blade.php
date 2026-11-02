<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-bold text-2xl text-slate-800 dark:text-white leading-tight flex items-center gap-2">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                {{ __('Gestión de Empleados') }}
            </h2>
            <a href="{{ route('admin.empleados.crear') }}" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-xl shadow-md transition-all text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo Empleado
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" x-data="{ viewMode: @js($filters['vista']) }">
            
            <section aria-label="Búsqueda y filtros de empleados" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 sm:p-6 shadow-sm">
                <form method="GET" action="{{ route('admin.empleados') }}" class="m-0 space-y-4">
                    <input type="hidden" name="vista" :value="viewMode" value="{{ $filters['vista'] }}">
                    <div>
                        <label for="employee-search" class="block text-base font-semibold text-slate-800 dark:text-slate-100 mb-2">¿A quién buscas?</label>
                        <input id="employee-search" type="search" name="buscar" maxlength="200" placeholder="Nombre, apellidos, DNI, correo o teléfono" value="{{ $filters['buscar'] }}"
                            class="w-full min-h-[44px] px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl text-slate-800 dark:text-slate-100 placeholder-slate-500 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700 dark:text-slate-200 mb-2">Mostrar empleados con contrato</legend>
                        <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
                            @foreach(['todos' => 'Todos', 'activos' => 'Activo', 'inactivos' => 'Finalizado'] as $value => $label)
                                <label class="cursor-pointer flex-1 sm:flex-none">
                                    <input type="radio" name="estado" value="{{ $value }}" @checked($filters['estado'] === $value) @change="$el.form.requestSubmit()" class="peer sr-only">
                                    <span class="flex min-h-[44px] items-center justify-center gap-1 sm:gap-2 [&_path]:invisible peer-checked:[&_path]:visible rounded-xl border border-slate-400 bg-white px-2 sm:px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 peer-checked:border-red-600 peer-checked:bg-red-50 peer-checked:text-red-700 peer-focus-visible:ring-2 peer-focus-visible:ring-red-500 peer-focus-visible:ring-offset-2 dark:border-slate-500 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-slate-800 dark:peer-checked:border-red-400 dark:peer-checked:bg-red-950 dark:peer-checked:text-red-200 dark:peer-focus-visible:ring-offset-slate-800">
                                        <svg aria-hidden="true" class="h-3 w-3 sm:h-4 sm:w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m7 12 3 3 7-7" /></svg>
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @php
                        $extraFilterCount = collect(['rol', 'situacion', 'disponibilidad'])->filter(fn ($key) => !empty($filters[$key]))->count();
                    @endphp
                    <details class="group rounded-xl border border-slate-200 dark:border-slate-700" @if($extraFilterCount) open @endif>
                        <summary class="flex min-h-[48px] cursor-pointer list-none flex-wrap items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-500 dark:text-slate-200 dark:hover:bg-slate-700/50 [&::-webkit-details-marker]:hidden">
                            <svg aria-hidden="true" class="h-4 w-4 shrink-0 group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                            Más filtros
                            @if($extraFilterCount)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-700">{{ $extraFilterCount }} {{ $extraFilterCount === 1 ? 'seleccionado' : 'seleccionados' }}</span>
                            @endif
                            <span class="w-full pl-6 font-normal text-slate-500 dark:text-slate-400 sm:w-auto sm:pl-0">Puesto, baja médica y disponibilidad</span>
                        </summary>
                        <div class="grid grid-cols-1 gap-4 border-t border-slate-200 p-4 sm:grid-cols-3 dark:border-slate-700">
                            <x-filter-select id="employee-role" name="rol" label="Puesto de trabajo"
                                :value="$filters['rol']"
                                :options="['' => 'Todos los puestos'] + array_combine(\App\Models\User::EMPLOYEE_ROLES, array_map('ucfirst', \App\Models\User::EMPLOYEE_ROLES))" />
                            <x-filter-select id="employee-leave" name="situacion" label="Baja médica"
                                :value="$filters['situacion']"
                                :options="['' => 'Todos', 'alta' => 'Sin baja médica', 'baja' => 'De baja médica']" />
                            <x-filter-select id="employee-availability" name="disponibilidad" label="Disponibilidad semanal"
                                :value="$filters['disponibilidad']"
                                :options="['' => 'Cualquier disponibilidad', 'completa' => 'Completa', 'personalizada' => 'Personalizada']" />
                        </div>
                    </details>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <button type="submit" class="min-h-[44px] px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">Buscar empleados</button>
                        <a href="{{ route('admin.empleados', ['estado' => 'todos', 'vista' => $filters['vista']]) }}" :href="@js(route('admin.empleados')) + '?estado=todos&vista=' + viewMode" class="min-h-[44px] inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-500">Ver todos los empleados</a>
                    </div>
                </form>
            </section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-300" role="status">
                    <strong class="text-slate-900 dark:text-white">{{ $empleados->total() }}</strong> {{ $empleados->total() === 1 ? 'empleado encontrado' : 'empleados encontrados' }}
                    @if($hasFilters)<span>con los filtros aplicados</span>@endif
                </p>
                <div class="flex bg-slate-100 dark:bg-slate-900/50 p-1 rounded-xl border border-slate-200 dark:border-slate-700" role="group" aria-label="Presentación de empleados">
                    <button type="button" aria-label="Ver empleados en tarjetas" aria-pressed="{{ $filters['vista'] === 'grid' ? 'true' : 'false' }}" :aria-pressed="viewMode === 'grid'" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-white dark:bg-slate-700 text-red-600 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'" class="min-h-[44px] px-3 rounded-lg focus-visible:ring-2 focus-visible:ring-red-500">Tarjetas</button>
                    <button type="button" aria-label="Ver empleados en lista" aria-pressed="{{ $filters['vista'] === 'list' ? 'true' : 'false' }}" :aria-pressed="viewMode === 'list'" @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-white dark:bg-slate-700 text-red-600 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400'" class="min-h-[44px] px-3 rounded-lg focus-visible:ring-2 focus-visible:ring-red-500">Lista</button>
                </div>
            </div>

            <!-- Empty State -->
            @if($empleados->isEmpty())
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl p-6 sm:p-12 flex flex-col items-center justify-center text-center shadow-sm mt-6">
                <div class="w-24 h-24 bg-slate-50 dark:bg-slate-900/50 rounded-full flex items-center justify-center mb-6 shadow-inner border border-slate-200 dark:border-slate-700">
                    <svg class="w-12 h-12 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Ningún empleado encontrado</h3>
                <p class="text-slate-500 dark:text-slate-400 max-w-md">Prueba otro nombre o pulsa «Ver todos los empleados» para empezar de nuevo.</p>
                <a href="{{ route('admin.empleados.crear') }}" class="mt-8 px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl transition-colors shadow-md">
                    Crear Empleado
                </a>
            </div>
            @else

            <!-- GRID VIEW -->
            <div x-show="viewMode === 'grid'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mt-6">
                @foreach($empleados as $emp)
                <div class="bg-white dark:bg-slate-800/60 border {{ !$emp->activo ? 'border-slate-400 dark:border-slate-500 opacity-75 grayscale' : ($emp->en_baja ? 'border-rose-300 dark:border-rose-500/30' : 'border-slate-200 dark:border-slate-700') }} rounded-2xl overflow-hidden hover:shadow-xl transition-all duration-300 hover:border-red-300 dark:hover:border-slate-500 flex flex-col group relative">
                    
                    <!-- Status Ribbon -->
                    @if(!$emp->activo)
                    <div class="absolute top-0 right-0 overflow-hidden w-24 h-24 z-10">
                        <div class="bg-slate-700 dark:bg-slate-600 text-white text-[10px] font-bold py-1 px-8 transform rotate-45 translate-x-4 translate-y-3 shadow-md uppercase tracking-wider text-center">
                            Inactivo
                        </div>
                    </div>
                    @elseif($emp->en_baja)
                    <div class="absolute top-0 right-0 overflow-hidden w-24 h-24 z-10">
                        <div class="bg-rose-500 text-white text-[10px] font-bold py-1 px-8 transform rotate-45 translate-x-4 translate-y-3 shadow-md uppercase tracking-wider text-center">
                            Baja
                        </div>
                    </div>
                    @endif

                    <div class="p-6 flex-1 flex flex-col items-center text-center relative z-0">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-black mb-4 shadow-inner ring-4 {{ !$emp->activo ? 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 ring-slate-400/20' : ($emp->en_baja ? 'bg-rose-100 dark:bg-rose-900/50 text-rose-600 dark:text-rose-300 ring-rose-500/20' : 'bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-300 ring-red-500/20') }}">
                            {{ strtoupper(substr($emp->nombre, 0, 1)) }}{{ $emp->apellidos ? strtoupper(substr($emp->apellidos, 0, 1)) : '' }}
                        </div>
                        
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white leading-tight mb-1">{{ $emp->nombre }} {{ $emp->apellidos }}</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm mb-4 truncate w-full">{{ $emp->email }}</p>
                        
                        <div class="mt-auto flex flex-col w-full gap-2">
                            @php 
                                $colores = ['repartidor'=>'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300 border-red-200 dark:border-red-500/30','encargado'=>'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300 border-rose-200 dark:border-rose-500/30','auxiliar'=>'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 border-amber-200 dark:border-amber-500/30','limpiadora'=>'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30','oficina'=>'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300 border-cyan-200 dark:border-cyan-500/30'];
                            @endphp
                            <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Rol</span>
                                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border {{ $colores[$emp->rol] ?? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600' }}">{{ ucfirst($emp->rol) }}</span>
                            </div>
                            
                            <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Contrato</span>
                                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $emp->horas_mensuales ? rtrim(rtrim(number_format($emp->horas_mensuales, 1), '0'), '.') . 'h/mes' : '—' }}</span>
                            </div>

                            <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Disp.</span>
                                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ ucfirst(str_replace('_', ' ', $emp->tipo_disponibilidad)) }}</span>
                            </div>
                            
                            @if($emp->vacaciones && count($emp->vacaciones) > 0)
                            <div class="flex items-center justify-between px-3 py-2 bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-500/20 rounded-lg">
                                <span class="text-xs text-sky-600 dark:text-sky-400/80 font-medium">Fechas Libres</span>
                                <span class="text-xs font-bold text-sky-700 dark:text-sky-300">{{ count($emp->vacaciones) }} d</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 p-3 grid grid-cols-1 gap-2 transition-colors duration-200">
                        <div class="grid grid-cols-2 gap-2">
                            <a href="{{ route('admin.empleados.editar', $emp->id) }}" class="flex items-center justify-center gap-2 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-red-600 dark:hover:bg-red-600 hover:text-white text-slate-700 dark:text-slate-200 rounded-lg text-sm font-medium transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Editar
                            </a>
                            <form method="POST" action="{{ route('admin.empleados.baja', $emp->id) }}" class="w-full m-0">
                                @csrf
                                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 {{ $emp->en_baja ? 'bg-emerald-100 hover:bg-emerald-500 text-emerald-700 hover:text-white dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-rose-100 hover:bg-rose-500 text-rose-700 hover:text-white dark:bg-rose-500/20 dark:text-rose-300' }} rounded-lg text-sm font-medium transition-colors" onclick="return confirm('¿Confirmar acción?')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $emp->en_baja ? 'M5 13l4 4L19 7' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' }}"></path></svg>
                                    {{ $emp->en_baja ? 'Dar Alta' : 'Dar Baja' }}
                                </button>
                            </form>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <form method="POST" action="{{ route('admin.empleados.activo', $emp->id) }}" class="w-full m-0">
                                @csrf
                                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 {{ !$emp->activo ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 dark:bg-slate-700 hover:bg-amber-600 hover:text-white text-slate-700 dark:text-slate-200' }} rounded-lg text-xs font-medium transition-colors" onclick="return confirm('¿Confirmar acción?')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if(!$emp->activo)
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                        @endif
                                    </svg>
                                    {{ !$emp->activo ? 'Reactivar' : 'Finalizar' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.empleados.eliminar', $emp->id) }}" class="w-full m-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-rose-600 hover:text-white text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium transition-colors" onclick="return confirm('¿Estás seguro de que deseas ELIMINAR a este empleado por completo? Esta acción no se puede deshacer.')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- LIST VIEW -->
            <div x-show="viewMode === 'list'" x-cloak class="bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 rounded-2xl overflow-hidden shadow-sm mt-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" style="font-size:14px;">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700">
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Empleado</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Rol</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Horas de contrato</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Disponibilidad</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Estado</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @foreach($empleados as $emp)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group {{ !$emp->activo ? 'opacity-75 grayscale bg-slate-50 dark:bg-slate-800/50' : ($emp->en_baja ? 'bg-rose-50 dark:bg-rose-900/10' : '') }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold shadow-inner flex-shrink-0 {{ !$emp->activo ? 'bg-slate-300 text-slate-500 dark:bg-slate-600 dark:text-slate-400' : ($emp->en_baja ? 'bg-rose-200 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300') }}">
                                            {{ strtoupper(substr($emp->nombre, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-white text-sm">{{ $emp->nombre }} {{ $emp->apellidos }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $emp->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full {{ $colores[$emp->rol] ?? 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">{{ ucfirst($emp->rol) }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300 text-sm">{{ $emp->horas_mensuales ? rtrim(rtrim(number_format($emp->horas_mensuales, 1), '0'), '.') : '—' }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ ucfirst(str_replace('_', ' ', $emp->tipo_disponibilidad)) }}</div>
                                    @if($emp->vacaciones && count($emp->vacaciones) > 0)
                                        <div class="text-[10px] text-sky-600 dark:text-sky-400 mt-1 font-semibold flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> {{ count($emp->vacaciones) }} días pedidos</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if(!$emp->activo)
                                        <div class="inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 border border-slate-300 text-slate-700 dark:bg-slate-700/50 dark:border-slate-600 dark:text-slate-400 text-xs font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                            Inactivo
                                        </div>
                                    @elseif($emp->en_baja)
                                        <div class="inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-full bg-rose-100 border border-rose-200 text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400 text-xs font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                            De Baja
                                        </div>
                                    @else
                                        <div class="inline-flex items-center justify-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 border border-emerald-200 text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 text-xs font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Activo
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 transition-colors">
                                        <a href="{{ route('admin.empleados.editar', $emp->id) }}" class="p-2 bg-slate-100 dark:bg-slate-700 hover:bg-red-600 text-slate-600 dark:text-slate-300 hover:text-white rounded-lg transition-colors shadow-sm" title="Editar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        
                                        <form method="POST" action="{{ route('admin.empleados.baja', $emp->id) }}" class="inline-block m-0">
                                            @csrf
                                            <button type="submit" onclick="return confirm('¿{{ $emp->en_baja ? 'Dar de alta' : 'Poner de baja' }} a {{ $emp->nombre }}?')" 
                                                    class="p-2 {{ $emp->en_baja ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-600/20 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white' : 'bg-rose-100 text-rose-600 dark:bg-rose-600/20 dark:text-rose-400 hover:bg-rose-600 hover:text-white' }} rounded-lg transition-colors shadow-sm border-0 cursor-pointer" title="{{ $emp->en_baja ? 'Alta' : 'Baja' }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($emp->en_baja)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    @endif
                                                </svg>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.empleados.activo', $emp->id) }}" class="inline-block m-0">
                                            @csrf
                                            <button type="submit" onclick="return confirm('¿Confirmar acción?')" 
                                                    class="p-2 {{ !$emp->activo ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 hover:bg-amber-600 hover:text-white' }} rounded-lg transition-colors shadow-sm border-0 cursor-pointer" title="{{ !$emp->activo ? 'Reactivar' : 'Finalizar' }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if(!$emp->activo)
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                    @endif
                                                </svg>
                                            </button>
                                        </form>

                                        @if(auth()->id() != $emp->id)
                                        <form method="POST" action="{{ route('admin.empleados.eliminar', $emp->id) }}" class="inline-block m-0">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-700 hover:bg-rose-600 text-slate-500 dark:text-slate-400 hover:text-white rounded-lg transition-colors shadow-sm border-0 cursor-pointer" onclick="return confirm('¿Eliminar a {{ $emp->nombre }}? Esta acción no se puede deshacer.')" title="Eliminar definitivamente">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="mt-6">
                {{ $empleados->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
