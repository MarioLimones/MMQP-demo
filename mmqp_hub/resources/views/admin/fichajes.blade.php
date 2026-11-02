<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-xl text-slate-800 dark:text-white leading-tight">
                {{ __('Control de Fichajes Diario') }}
            </h2>
            <form action="{{ route('admin.fichajes.index') }}" method="GET" class="flex items-center gap-2">
                <input aria-label="Fecha de los fichajes" type="date" name="fecha" value="{{ $fechaStr }}" class="border-slate-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-red-500 focus:ring-red-500 rounded-xl shadow-sm text-sm" style="color-scheme: dark;">
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-sm transition-colors shadow-sm">
                    Ver Día
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-12" x-data="fichajesData()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($errors->any())
                <div class="bg-rose-50 dark:bg-rose-500/10 border-l-4 border-rose-500 p-4 rounded-xl shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-rose-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300">Hay errores:</h3>
                            <ul class="mt-2 text-sm text-rose-700 dark:text-rose-400 list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
            
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 p-4 rounded-xl shadow-sm transition-all duration-500">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- PANEL 1: Trabajan Hoy -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-extrabold text-slate-800 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Trabajan Hoy (Día: {{ \Carbon\Carbon::parse($fechaStr)->format('d/m/Y') }})
                    </h3>
                </div>

                @if(count($trabajanHoy) > 0)
                    @foreach($trabajanHoy as $rol => $empleados)
                        <div class="mt-8 mb-4 flex items-center gap-3">
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                            <h4 class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest">{{ $rol }}</h4>
                            <div class="h-px bg-slate-200 dark:bg-slate-700 flex-1"></div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            @foreach($empleados as $emp)
                                <div @click="openDetalleEmpleadoModal({{ $emp->id }})" class="cursor-pointer bg-white dark:bg-slate-800 p-5 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 hover:shadow-md hover:border-red-400 dark:hover:border-red-500 transition-all group flex flex-col items-center text-center h-full">
                                    <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-black text-xl mb-3 group-hover:scale-110 transition-transform">
                                        {{ substr($emp->nombre, 0, 1) }}
                                    </div>
                                    <div class="font-bold text-slate-800 dark:text-white text-base">{{ $emp->nombre }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold uppercase mt-1">{{ $emp->rol }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xl sm:rounded-3xl border border-slate-100 dark:border-slate-700 p-12 text-center text-slate-500 dark:text-slate-400">
                        No hay empleados trabajando este día según el horario (y nadie ha fichado).
                    </div>
                @endif
            </div>

            <!-- PANEL 2: No Trabajan Hoy -->
            @if(count($noTrabajanHoy) > 0)
            <div class="mt-12">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-extrabold text-slate-800 dark:text-white flex items-center gap-2 opacity-60">
                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                        Resto de Plantilla (No programados hoy)
                    </h3>
                </div>

                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xl sm:rounded-3xl border border-slate-100 dark:border-slate-700 opacity-80">
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($noTrabajanHoy as $rol => $empleados)
                            <div class="bg-slate-50 dark:bg-slate-900/50 px-6 py-2 border-b border-slate-100 dark:border-slate-700">
                                <h4 class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest">{{ $rol }}</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-0 divide-y md:divide-y-0 md:divide-x divide-slate-100 dark:divide-slate-700/50">
                                @foreach($empleados as $emp)
                                    <div class="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                        <div>
                                            <div class="font-bold text-slate-700 dark:text-slate-300">{{ $emp->nombre }} {{ $emp->apellidos }}</div>
                                            <div class="text-xs text-slate-700 dark:text-slate-300 font-semibold uppercase">{{ $emp->rol }}</div>
                                        </div>
                                        <button @click="openAsignarTurnoModal({{ $emp->id }}, '{{ addslashes($emp->nombre) }}')" class="p-2 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 rounded-xl transition" title="Asignar turno extra para hoy">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

        </div>

        <!-- Modal Detalles Empleado -->
        <div x-show="detalleModalOpen" style="display: none;" class="fixed inset-0 z-40 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <div x-show="detalleModalOpen" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div x-show="detalleModalOpen" class="inline-flex flex-col align-bottom bg-slate-50 dark:bg-slate-900 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-[90vw] w-full min-h-[60vh] border border-slate-200 dark:border-slate-700">
                    
                    <!-- Encabezado del modal -->
                    <div class="bg-white dark:bg-slate-800 px-6 py-5 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center relative overflow-hidden">
                        <div class="relative z-10 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center text-red-600 dark:text-red-400 font-black text-xl">
                                <span x-text="selectedEmp.nombre.substring(0,1)"></span>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-800 dark:text-white" x-text="selectedEmp.nombre"></h3>
                                <p class="text-sm font-semibold text-slate-500 uppercase tracking-widest" x-text="selectedEmp.rol"></p>
                            </div>
                        </div>
                        <button @click="closeDetalleModal()" class="relative z-10 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Columna Teórico -->
                        <div>
                            <h4 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Turno Teórico
                            </h4>
                            <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                                <template x-if="selectedEmp.teoricos.length > 0">
                                    <div>
                                        <template x-for="teorico in selectedEmp.teoricos">
                                            <div class="flex items-center gap-2 mb-2 text-slate-700 dark:text-slate-300 font-bold text-lg">
                                                <span x-text="teorico.inicio"></span>
                                                <span class="text-slate-400 text-sm">a</span>
                                                <span x-text="teorico.fin"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="selectedEmp.teoricos.length === 0">
                                    <span class="text-sm text-slate-400 italic">No tiene turno programado hoy.</span>
                                </template>
                            </div>
                        </div>

                        <!-- Columna Fichajes -->
                        <div class="relative">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Fichaje
                                </h4>
                                <template x-if="formMode === 'list'">
                                    <button @click="startCreate()" class="text-[10px] bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 px-2 py-1 rounded font-bold uppercase hover:bg-emerald-200 dark:hover:bg-emerald-500/40 transition">
                                        + Añadir Manual
                                    </button>
                                </template>
                            </div>

                            <!-- Listado de fichajes -->
                            <div x-show="formMode === 'list'" class="space-y-3">
                                <template x-for="f in selectedEmp.reales" :key="f.id">
                                    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                                        <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 font-bold text-lg">
                                            <span class="text-emerald-500" x-text="formatTimeOnly(f.entrada_local || f.entrada)"></span>
                                            <span class="text-slate-300 dark:text-slate-600 text-sm">a</span>
                                            <template x-if="f.salida">
                                                <span class="text-rose-500" x-text="formatTimeOnly(f.salida_local || f.salida)"></span>
                                            </template>
                                            <template x-if="!f.salida">
                                                <span class="text-amber-500 text-sm animate-pulse">Activo...</span>
                                            </template>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="startEdit(f)" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <form :action="'/admin/fichajes/' + f.id" method="POST" onsubmit="return confirm('¿Eliminar este registro de fichaje?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 rounded-lg transition">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="selectedEmp.reales.length === 0">
                                    <div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm text-center">
                                        <span class="text-sm text-rose-500 dark:text-rose-400 font-bold">Aún no ha fichado entrada</span>
                                    </div>
                                </template>
                            </div>

                            <!-- Formulario (Crear/Editar) -->
                            <div x-show="formMode !== 'list'" style="display: none;" class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4" x-text="formMode === 'create' ? 'Nuevo Fichaje Manual' : 'Editando Fichaje'"></h4>
                                <form @submit.prevent="submitForm()" class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div x-data="{ open: false }" class="relative">
                                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Entrada</label>
                                            <button @click="open = !open" type="button" class="w-full text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2 flex justify-between items-center text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500 shadow-sm transition">
                                                <span x-text="editingForm.entrada || '--:--'"></span>
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" style="display: none;" class="absolute z-10 w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl max-h-60 overflow-y-auto shadow-xl custom-scrollbar">
                                                <template x-for="t in timeOptions" :key="t">
                                                    <button type="button" @click="editingForm.entrada = t; open = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold transition" :class="{'bg-red-50 dark:bg-red-900/50 text-red-600 dark:text-red-400': editingForm.entrada === t}">
                                                        <span x-text="t"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                        <div x-data="{ open: false }" class="relative">
                                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Salida <span class="font-normal opacity-70">(Opcional)</span></label>
                                            <button @click="open = !open" type="button" class="w-full text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2 flex justify-between items-center text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-red-500 shadow-sm transition">
                                                <span x-text="editingForm.salida || '-- Sin salida --'"></span>
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" style="display: none;" class="absolute z-10 w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl max-h-60 overflow-y-auto shadow-xl custom-scrollbar">
                                                <button type="button" @click="editingForm.salida = ''; open = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 text-sm font-semibold italic border-b border-slate-100 dark:border-slate-700 transition">
                                                    -- Sin salida --
                                                </button>
                                                <template x-for="t in timeOptions" :key="t">
                                                    <button type="button" @click="editingForm.salida = t; open = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold transition" :class="{'bg-red-50 dark:bg-red-900/50 text-red-600 dark:text-red-400': editingForm.salida === t}">
                                                        <span x-text="t"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-2 pt-2">
                                        <button type="button" @click="formMode = 'list'" class="flex-1 px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-sm font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition">Cancelar</button>
                                        <button type="submit" :disabled="isSubmitting" class="flex-1 px-4 py-2 bg-red-600 text-white text-sm font-bold rounded-xl hover:bg-red-700 transition disabled:opacity-50">
                                            <span x-text="isSubmitting ? 'Guardando...' : 'Guardar'"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Asignar Turno Extra -->
        <div x-show="asignarTurnoFichaje !== null" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <div x-show="asignarTurnoFichaje !== null" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="asignarTurnoFichaje !== null" class="inline-flex flex-col align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-slate-100 dark:border-slate-700">
                    <form @submit.prevent="submitExtra($event)" :action="'/admin/fichajes/extra/' + asignarTurnoFichaje" method="POST" class="flex flex-col h-full">
                        @csrf
                        <input type="hidden" name="fecha" value="{{ $fechaStr }}">
                        <div class="bg-gradient-to-r from-emerald-50 to-white dark:from-slate-800 dark:to-slate-800 px-6 py-5 border-b border-emerald-100/50 dark:border-slate-700">
                            <h3 class="text-xl font-extrabold text-slate-800 dark:text-white" id="modal-title">Asignar Turno a <span x-text="asignarTurnoNombre"></span></h3>
                            <p class="text-sm text-slate-500 mt-1">Este turno se guardará en un borrador del {{ \Carbon\Carbon::parse($fechaStr)->format('d/m/Y') }}. Tendrás que publicarlo explícitamente para que lo vea el empleado.</p>
                            <p role="status" aria-live="polite" x-show="extraLoading">Preparando borrador…</p>
                            <p role="alert" x-show="extraError" x-text="extraError" class="mt-2 text-red-700"></p>
                        </div>
                        <div class="px-6 py-6 flex-1 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                        <div x-data="{ open: false }" class="relative">
                                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Hora Inicio</label>
                                            <button @click="open = !open" type="button" class="w-full text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2 flex justify-between items-center text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500 shadow-sm transition">
                                                <span x-text="$el.nextElementSibling.value || '13:00'"></span>
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <input type="hidden" name="hora_inicio" value="13:00">
                                            <div x-show="open" @click.away="open = false" style="display: none;" class="absolute z-10 w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl max-h-60 overflow-y-auto shadow-xl custom-scrollbar">
                                                <template x-for="t in timeOptions" :key="t">
                                                    <button type="button" @click="$el.closest('div.relative').querySelector('input').value = t; $el.closest('div.relative').querySelector('span').innerText = t; open = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold transition">
                                                        <span x-text="t"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                        <div x-data="{ open: false }" class="relative">
                                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Hora Fin</label>
                                            <button @click="open = !open" type="button" class="w-full text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2 flex justify-between items-center text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500 shadow-sm transition">
                                                <span x-text="$el.nextElementSibling.value || '18:00'"></span>
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <input type="hidden" name="hora_fin" value="18:00">
                                            <div x-show="open" @click.away="open = false" style="display: none;" class="absolute z-10 w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl max-h-60 overflow-y-auto shadow-xl custom-scrollbar">
                                                <template x-for="t in timeOptions" :key="t">
                                                    <button type="button" @click="$el.closest('div.relative').querySelector('input').value = t; $el.closest('div.relative').querySelector('span').innerText = t; open = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold transition">
                                                        <span x-text="t"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-700/50 px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3 rounded-b-3xl">
                            <button type="button" @click="asignarTurnoFichaje = null" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-sm">Cancelar</button>
                            <button type="submit" :disabled="extraLoading || !extraDraft || !!extraError" class="px-5 py-2.5 bg-emerald-600 border border-transparent rounded-xl text-sm font-bold text-white hover:bg-emerald-700 transition-colors shadow-sm shadow-emerald-500/30">Guardar turno en borrador</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Los modales antiguos (Crear/Editar) se han eliminado ya que ahora son inline -->

    </div>

    @push('scripts')
    <script src="{{ asset('js/schedule-draft-session.js') }}"></script>
    <script>
        function fichajesData() {
            return {
                empleadosDict: {!! json_encode($empleadosDict) !!},
                fechaSeleccionada: '{{ $fechaStr }}',
                detalleModalOpen: false,
                formMode: 'list', // 'list', 'create', 'edit'
                isSubmitting: false,
                selectedEmp: {
                    id: null,
                    nombre: '',
                    rol: '',
                    teoricos: [],
                    reales: []
                },
                asignarTurnoFichaje: null,
                asignarTurnoNombre: '',
                extraDraft: null,
                extraLoading: false,
                extraError: '',
                extraRequestId: '',
                editingForm: {
                    id: null,
                    entrada: '',
                    salida: ''
                },
                timeOptions: [
                    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', 
                    '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', 
                    '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', 
                    '21:00', '21:30', '22:00', '22:30', '23:00', '23:30', '00:00', '00:30', 
                    '01:00', '01:30', '02:00', '02:30', '03:00', '03:30', '04:00'
                ],
                formatTimeOnly(dateString) {
                    if (!dateString) return '';
                    if (dateString.includes('T')) return dateString.split('T')[1].substring(0, 5);
                    if (dateString.includes(' ')) return dateString.split(' ')[1].substring(0, 5);
                    return dateString.substring(0, 5);
                },
                openDetalleEmpleadoModal(id) {
                    let emp = this.empleadosDict[id];
                    this.selectedEmp = {
                        id: emp.id,
                        nombre: emp.nombre + (emp.apellidos ? ' ' + emp.apellidos : ''),
                        rol: emp.rol,
                        teoricos: emp.turnos_teoricos || [],
                        reales: emp.fichajes_reales || []
                    };
                    this.formMode = 'list';
                    this.detalleModalOpen = true;
                },
                closeDetalleModal() {
                    this.detalleModalOpen = false;
                },
                startCreate() {
                    // Pre-rellenamos las 13:00 o 20:00 como sugerencia central para que el desplegable comience mas cerca.
                    this.editingForm = { id: null, entrada: '13:00', salida: '' };
                    this.formMode = 'create';
                },
                startEdit(fichaje) {
                    let ent = this.formatTimeOnly(fichaje.entrada_local || fichaje.entrada);
                    let sal = this.formatTimeOnly(fichaje.salida_local || fichaje.salida);
                    // Aproximar a la franja más cercana si no coincide exactamente con las de 30 mins
                    if (!this.timeOptions.includes(ent)) ent = this.roundToNearest30(ent);
                    if (sal && !this.timeOptions.includes(sal)) sal = this.roundToNearest30(sal);

                    this.editingForm = { 
                        id: fichaje.id, 
                        entrada: ent, 
                        salida: sal
                    };
                    this.formMode = 'edit';
                },
                roundToNearest30(timeStr) {
                    if(!timeStr) return '';
                    let [h, m] = timeStr.split(':');
                    m = parseInt(m);
                    if(m < 15) m = '00';
                    else if (m < 45) m = '30';
                    else { m = '00'; h = String(parseInt(h) + 1).padStart(2, '0'); if(h=='24') h='00'; }
                    return h + ':' + m;
                },
                async submitForm() {
                    this.isSubmitting = true;
                    try {
                        const url = this.formMode === 'create' 
                            ? '/admin/fichajes' 
                            : `/admin/fichajes/${this.editingForm.id}`;
                            
                        const method = this.formMode === 'create' ? 'POST' : 'PUT';
                        
                        const body = {
                            _token: document.querySelector('meta[name="csrf-token"]').content,
                            user_id: this.selectedEmp.id,
                            fecha: this.fechaSeleccionada,
                            entrada: this.editingForm.entrada,
                            salida: this.editingForm.salida
                        };

                        if (method === 'PUT') body._method = 'PUT';

                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            let newReales = [...this.selectedEmp.reales];
                            if (this.formMode === 'create') {
                                newReales.push(data.fichaje);
                            } else {
                                const index = newReales.findIndex(f => f.id === data.fichaje.id);
                                if (index !== -1) {
                                    newReales[index] = data.fichaje;
                                }
                            }
                            // Sort by entry time using string comparison to avoid TZ issues
                            newReales.sort((a,b) => a.entrada.localeCompare(b.entrada));
                            
                            // Reassign to trigger Alpine reactivity
                            this.selectedEmp.reales = newReales;
                            this.empleadosDict[this.selectedEmp.id].fichajes_reales = newReales;
                            
                            this.formMode = 'list';
                        } else {
                            alert('Error al guardar: ' + (data.message || 'Verifica los datos'));
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    } finally {
                        this.isSubmitting = false;
                    }
                },
                async openAsignarTurnoModal(userId, nombre) {
                    this.asignarTurnoFichaje = userId;
                    this.asignarTurnoNombre = nombre;
                    this.extraDraft = null;
                    this.extraError = '';
                    this.extraLoading = true;
                    this.extraRequestId = crypto.randomUUID();
                    try {
                        this.extraDraft = await ScheduleDraftSession.open('{{ \Carbon\Carbon::parse($fechaStr)->format('o-\WW') }}');
                    } catch (error) {
                        this.extraError = error.message + ' Cierra y vuelve a abrir para revisar el borrador.';
                    } finally { this.extraLoading = false; }
                },
                async submitExtra(event) {
                    if (!this.extraDraft || this.extraLoading) return;
                    const fields = Object.fromEntries(new FormData(event.target));
                    const body = { ...fields, draft_id: this.extraDraft.id, version: this.extraDraft.version,
                        request_id: this.extraRequestId };
                    this.extraLoading = true;
                    try {
                        const result = await ScheduleDraftSession.request(event.target.action, body);
                        window.location.href = result.redirect_url;
                    } catch (error) {
                        this.extraError = error.message + ' Cierra y vuelve a abrir para revisar el borrador.';
                        this.extraLoading = false;
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
