<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight flex items-center gap-2">
                Gestor de Vacaciones y Días Libres
            </h2>
            <div class="flex gap-4 items-center" x-data>
                <button @click="window.dispatchEvent(new CustomEvent('abrir-modal-fecha', { detail: '{{ now()->toDateString() }}' }))" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 dark:bg-white border border-transparent rounded-xl font-bold text-xs text-white dark:text-slate-800 uppercase tracking-widest hover:bg-slate-700 dark:hover:bg-slate-200 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Asignar Manualmente
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="gestorVacaciones()" @abrir-modal-fecha.window="abrirModal($event.detail)">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="mb-6 bg-emerald-500/20 border border-emerald-500 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="font-bold">{{ session('success') }}</span>
                </div>
            @endif

            @include('components.absence-feedback')
            @if ($historicalCount > 0)
                <p role="status" class="mb-4 text-amber-800 dark:text-amber-200">Hay {{ $historicalCount }} fechas con bloqueos históricos cuyo origen requiere revisión. Se conservan en disponibilidad y no se consideran vacaciones aprobadas.</p>
            @endif
            <div class="mb-4 flex gap-4 items-center justify-end px-4 sm:px-0">
                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 font-medium">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block shadow-sm"></span> Aprobadas
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 font-medium">
                    <span class="w-3 h-3 rounded-full bg-amber-500 inline-block shadow-sm"></span> Pendientes
                </div>
            </div>

            <div class="absence-calendar-panel">
                <div class="absence-calendar-toolbar">
                    <div class="absence-calendar-arrows">
                        <button type="button" id="absence-prev" aria-label="Periodo anterior"><svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 5-7 7 7 7"/></svg></button>
                        <button type="button" id="absence-next" aria-label="Periodo siguiente"><svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 5 7 7-7 7"/></svg></button>
                    </div>
                    <h3 id="absence-calendar-title" aria-live="polite"></h3>
                    <div class="absence-calendar-actions">
                        <div class="absence-calendar-views" role="group" aria-label="Vista del calendario">
                            <button type="button" id="absence-month-view" aria-pressed="true">Mes</button>
                            <button type="button" id="absence-week-view" aria-pressed="false">Semana</button>
                        </div>
                        <input type="month" id="absence-month-picker" aria-label="Elegir mes del calendario">
                    </div>
                </div>
                <p class="absence-calendar-scroll-hint">Desliza para ver todos los días de la semana.</p>
                <div class="absence-calendar-scroll">
                    <div id="calendar" class="absence-calendar w-full"></div>
                </div>
            </div>
            
            <div class="mt-6 text-center">
                <a href="{{ route('admin.solicitudes') }}" class="inline-flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-300 font-medium transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Ir al Buzón de Solicitudes para aprobar/rechazar
                </a>
            </div>
        </div>

        <!-- Modal de Asignación Manual -->
        <div x-show="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="modalOpen" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed inset-0 transition-opacity" aria-hidden="true" @click="modalOpen = false">
                    <div class="absolute inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div x-show="modalOpen" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-slate-200 dark:border-slate-700">
                    
                    <form action="{{ route('admin.vacaciones.asignar') }}" method="POST">
                        @csrf
                        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="px-6 py-6 sm:px-8 sm:py-8">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="p-3 rounded-2xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-black text-slate-800 dark:text-white">Asignar Día Libre</h3>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Asignación manual por administrador</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="absence-user_id-1" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Empleado</label>
                                    <select id="absence-user_id-1" name="user_id" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                        <option value="">Selecciona un empleado...</option>
                                        @foreach($empleados as $emp)
                                            <option value="{{ $emp->id }}" @selected(old('user_id') == $emp->id)>{{ $emp->nombre }} {{ $emp->apellidos }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="absence-tipo-2" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Tipo de Ausencia</label>
                                    <select id="absence-tipo-2" name="tipo" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                        <option value="dia_libre" @selected(old('tipo', 'dia_libre') === 'dia_libre')>Día Libre</option>
                                        <option value="vacaciones" @selected(old('tipo') === 'vacaciones')>Vacaciones</option>
                                        <option value="baja" @selected(old('tipo') === 'baja')>Baja Médica</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="absence-fecha-3" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Fecha Inicio</label>
                                        <input id="absence-fecha-3" type="date" name="fecha" x-model="fechaSeleccionada" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label for="absence-fecha_fin-4" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Fecha Fin (Opcional)</label>
                                        <input id="absence-fecha_fin-4" type="date" name="fecha_fin" value="{{ old('fecha_fin') }}" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                        <p class="text-xs text-slate-500 mt-1">Déjalo vacío para un solo día</p>
                                    </div>
                                </div>
                                <div>
                                    <label for="absence-motivo_empleado-5" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Motivo / Notas (Opcional)</label>
                                    <input id="absence-motivo_empleado-5" type="text" name="motivo_empleado" value="{{ old('motivo_empleado') }}" placeholder="Ej: Compensación horas extra" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 sm:flex sm:flex-row-reverse gap-3 rounded-b-3xl">
                            <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 font-bold text-white rounded-xl shadow-lg transition-colors bg-indigo-600 hover:bg-indigo-500 shadow-indigo-500/30">
                                Confirmar Asignación
                            </button>
                            <button type="button" @click="modalOpen = false" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-xl border border-slate-300 dark:border-slate-600 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- MODAL DE EDICIÓN / ELIMINACIÓN -->
        <div x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" @abrir-modal-editar.window="abrirEditModal($event.detail)">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="editModalOpen" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed inset-0 transition-opacity" aria-hidden="true" @click="editModalOpen = false">
                    <div class="absolute inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div x-show="editModalOpen" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-slate-200 dark:border-slate-700 relative">
                    
                    <form :action="`/admin/vacaciones/${editData.id}`" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="version" :value="editData.version">
                        <input type="hidden" name="absence_id" :value="editData.id">
                        <div class="px-6 py-6 sm:px-8 sm:py-8">
                            <div class="flex justify-between items-start mb-6">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 rounded-2xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-black text-slate-800 dark:text-white" x-text="'Editar ' + editData.nombre"></h3>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Modificar registro existente</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="absence-tipo-6" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Tipo de Ausencia</label>
                                    <select id="absence-tipo-6" name="tipo" x-model="editData.tipo" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                        <option value="dia_libre">Día Libre</option>
                                        <option value="vacaciones">Vacaciones</option>
                                        <option value="baja">Baja Médica</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="absence-fecha-7" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Fecha Inicio</label>
                                        <input id="absence-fecha-7" type="date" name="fecha" x-model="editData.fecha" required class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                    </div>
                                    <div>
                                        <label for="absence-fecha_fin-8" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Fecha Fin</label>
                                        <input id="absence-fecha_fin-8" type="date" name="fecha_fin" x-model="editData.fecha_fin" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label for="absence-motivo_empleado-9" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Motivo / Notas</label>
                                    <input id="absence-motivo_empleado-9" type="text" name="motivo_empleado" x-model="editData.motivo" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 sm:flex sm:flex-row-reverse gap-3 rounded-b-3xl">
                            <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 font-bold text-white rounded-xl shadow-lg transition-colors bg-indigo-600 hover:bg-indigo-500 shadow-indigo-500/30">
                                Guardar Cambios
                            </button>
                            <button type="button" @click="editModalOpen = false" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-xl border border-slate-300 dark:border-slate-600 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>

                    <!-- Botón Eliminar Independiente -->
                    <form :action="`/admin/solicitudes/${editData.id}`" method="POST" class="px-6 pb-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="version" :value="editData.version">
                        <input type="hidden" name="estado" value="cancelada">
                        <button type="submit" class="underline text-rose-700 dark:text-rose-300">Cancelar esta ausencia</button>
                    </form>
                    <form :action="`/admin/vacaciones/${editData.id}`" method="POST" class="absolute top-6 right-6" onsubmit="return confirm('¿Estás seguro de eliminar este registro?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="version" :value="editData.version">
                        <button type="submit" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors" title="Eliminar Registro">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <!-- FullCalendar CSS y JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/locales/es.global.min.js'></script>

    <style>
        @media screen {
            html:has(.absence-calendar-panel) { scrollbar-gutter: stable; }
        }
        /* Keep FullCalendar's sticky surfaces and nested tables in the same theme. */
        .absence-calendar-panel { min-width: 0; padding: 24px; border: 0; border-radius: 16px; background: #fff; box-shadow: none; }
        .dark .absence-calendar-panel { background: #1e293b; border-color: #334155; }
        .absence-calendar-toolbar { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 16px; margin-bottom: 16px; }
        .absence-calendar-arrows { display: flex; gap: 6px; }
        .absence-calendar-arrows button { display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; }
        .dark .absence-calendar-arrows button { background: #6366f1; }
        .absence-calendar-arrows button:hover { background: #4338ca; }
        #absence-calendar-title { margin: 0; color: #1e293b; font-size: 20px; font-weight: 800; text-align: center; text-transform: capitalize; }
        .dark #absence-calendar-title { color: #f8fafc; }
        .absence-calendar-actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; }
        .absence-calendar-views { position: relative; display: grid; grid-template-columns: repeat(2, 1fr); padding: 3px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .absence-calendar-views::before { content: ''; position: absolute; top: 3px; bottom: 3px; left: 3px; width: calc((100% - 6px) / 2); border-radius: 5px; background: #4f46e5; pointer-events: none; transform: translateX(0); transition: transform 200ms cubic-bezier(.2, 0, 0, 1); }
        .absence-calendar-views:has(#absence-week-view[aria-pressed=true])::before { transform: translateX(100%); }
        .absence-calendar-views button { position: relative; z-index: 1; min-height: 32px; padding: 4px 10px; border-radius: 5px; font-size: 13px; font-weight: 600; color: #475569; transition: color 150ms ease; }
        .absence-calendar-views button[aria-pressed=true] { color: #fff; }
        #absence-month-picker { width: 200px; min-height: 40px; padding: 8px 12px; border: 1px solid #94a3b8; border-radius: 8px; background: #f8fafc; color: #0f172a; font: inherit; font-size: 14px; }
        .dark .absence-calendar-views { border-color: #475569; }
        .dark .absence-calendar-views button { color: #cbd5e1; }
        .dark .absence-calendar-views::before { background: #6366f1; }
        .dark .absence-calendar-views button[aria-pressed=true] { color: #fff; }
        .dark #absence-month-picker { color-scheme: dark; background: #334155; border-color: #64748b; color: #fff; }
        .absence-calendar-toolbar :is(button,input):focus-visible { outline: 2px solid #818cf8; outline-offset: 3px; }
        @media (max-width: 1100px) {
            .absence-calendar-toolbar { grid-template-columns: auto 1fr; }
            #absence-calendar-title { grid-column: 1 / -1; grid-row: 1; }
        }
        @media (max-width: 639px) {
            .absence-calendar-panel { padding: 16px; }
            .absence-calendar-actions { flex-wrap: wrap; gap: 8px; }
            #absence-month-picker { width: 170px; }
        }
        .absence-calendar {
            --fc-page-bg-color: #fff;
            --fc-neutral-bg-color: #f8fafc;
            --fc-border-color: #e2e8f0;
            --fc-today-bg-color: transparent;
            --absence-day-hover: #f8fafc;
            --absence-heading-ink: #475569;
            --absence-heading-fill: #f8fafc;
            min-width: 840px;
        }
        .dark .absence-calendar {
            --fc-page-bg-color: #1e293b;
            --fc-neutral-bg-color: #1e293b;
            --fc-border-color: #334155;
            --fc-today-bg-color: transparent;
            --absence-day-hover: #233044;
            --absence-heading-ink: #cbd5e1;
            --absence-heading-fill: #1e293b;
        }
        .absence-calendar .fc-scrollgrid {
            border: 1px solid var(--fc-border-color);
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: none;
        }
        .absence-calendar .fc-view-harness { background: var(--fc-page-bg-color); border-radius: 0.75rem; overflow: hidden; }
        .absence-calendar th, .absence-calendar td { border-color: var(--fc-border-color); }
        .absence-calendar .fc-daygrid-day {
            cursor: pointer; /* Indica que se puede clicar */
        }
        .absence-calendar .fc-daygrid-day:not(.fc-day-today):hover {
            background-color: var(--absence-day-hover);
        }
        .absence-calendar .fc-toolbar-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
        }
        .dark .absence-calendar .fc-toolbar-title {
            color: #f8fafc;
        }
        .absence-calendar .fc-button-primary {
            background-color: #334155 !important;
            border-color: #334155 !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            text-transform: capitalize;
        }
        .absence-calendar .fc-button-primary:hover {
            background-color: #0f172a !important;
        }
        .absence-calendar .fc-day-today { background-color: var(--fc-today-bg-color); }
        .absence-calendar-scroll { overflow-x: auto; container-type: inline-size; }
        .absence-calendar-scroll-hint { display: none; }
        .absence-calendar .fc-daygrid-day-frame { min-height: 118px; }
        .absence-calendar .fc-col-header,
        .absence-calendar .fc-col-header-cell,
        .absence-calendar .fc-scrollgrid-section-header > th { background: var(--absence-heading-fill); }
        .absence-calendar .fc-col-header-cell-cushion { padding: 5px 4px; color: var(--absence-heading-ink); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .absence-calendar .fc-daygrid-day-number { padding: 4px 8px; font-size: 14px; font-weight: 600; color: #334155; }
        .dark .absence-calendar .fc-daygrid-day-number { color: #f8fafc; }
        .absence-calendar .fc-day-other .fc-daygrid-day-number { color: #64748b; }
        .dark .absence-calendar .fc-day-other .fc-daygrid-day-number { color: #94a3b8; }
        .absence-calendar .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; margin: 4px; padding: 0; border-radius: 50%; background: #4f46e5; color: #fff; font-weight: 700; }
        .dark .absence-calendar .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { background: #6366f1; color: #fff; }
        .absence-calendar .absence-event {
            --absence-accent: #1d4ed8;
            --absence-fill: #eff6ff;
            --absence-line: #bfdbfe;
            --absence-text: #1e3a8a;
            --absence-meta: #1e40af;
            container-type: inline-size;
            cursor: pointer;
            border: 1px solid var(--absence-line) !important;
            border-radius: 0;
            padding: 0;
            margin: 3px 2px;
            background: var(--absence-fill) !important;
            box-shadow: none;
            transition: border-color .18s ease, background-color .18s ease;
        }
        .absence-calendar .absence-event[data-absence-tone=free] {
            --absence-accent: #047857; --absence-fill: #ecfdf5; --absence-line: #a7f3d0;
            --absence-text: #064e3b; --absence-meta: #065f46;
        }
        .absence-calendar .absence-event[data-absence-tone=sick] {
            --absence-accent: #be123c; --absence-fill: #fff1f2; --absence-line: #fecdd3;
            --absence-text: #881337; --absence-meta: #9f1239;
        }
        .absence-calendar .absence-event[data-absence-tone=pending] {
            --absence-accent: #92632d; --absence-fill: #fff8ed; --absence-line: #e7d6bb;
            --absence-text: #71491c; --absence-meta: #805525;
        }
        .absence-calendar .absence-event[data-absence-tone=historical] {
            --absence-accent: #475569; --absence-fill: #f1f5f9; --absence-line: #cbd5e1;
            --absence-text: #1e293b; --absence-meta: #475569;
        }
        .dark .absence-calendar .absence-event {
            --absence-accent: #93c5fd; --absence-fill: #253954; --absence-line: #3c5679;
            --absence-text: #eff6ff; --absence-meta: #bfdbfe;
        }
        .dark .absence-calendar .absence-event[data-absence-tone=free] {
            --absence-accent: #6ee7b7; --absence-fill: #203e3b; --absence-line: #35635a;
            --absence-text: #ecfdf5; --absence-meta: #a7f3d0;
        }
        .dark .absence-calendar .absence-event[data-absence-tone=sick] {
            --absence-accent: #fda4af; --absence-fill: #442d3d; --absence-line: #704251;
            --absence-text: #fff1f2; --absence-meta: #fecdd3;
        }
        .dark .absence-calendar .absence-event[data-absence-tone=pending] {
            --absence-accent: #e7c99b; --absence-fill: #39352e; --absence-line: #665641;
            --absence-text: #fff7ed; --absence-meta: #e7c99b;
        }
        .dark .absence-calendar .absence-event[data-absence-tone=historical] {
            --absence-accent: #cbd5e1; --absence-fill: #334155; --absence-line: #64748b;
            --absence-text: #f8fafc; --absence-meta: #e2e8f0;
        }
        .absence-calendar .absence-event.fc-event-start {
            border-start-start-radius: 8px;
            border-end-start-radius: 8px;
        }
        .absence-calendar .absence-event.fc-event-end {
            border-start-end-radius: 8px;
            border-end-end-radius: 8px;
        }
        .absence-calendar .absence-event:hover { border-color: var(--absence-accent) !important; }
        .absence-calendar .absence-event:focus-visible { outline: 2px solid var(--absence-accent); outline-offset: 2px; }
        .absence-calendar .absence-event .fc-event-main { color: var(--absence-text); }
        .absence-event-content { display: flex; flex-direction: column; gap: 3px; min-height: 48px; padding: 7px 9px; line-height: 1.25; }
        .absence-event-heading { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .absence-event-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; font-weight: 700; }
        .absence-event-duration { flex-shrink: 0; border: 1px solid var(--absence-line); border-radius: 5px; padding: 2px 5px; font-size: 12px; font-weight: 600; color: var(--absence-text); font-variant-numeric: tabular-nums; }
        .absence-event-meta { display: flex; gap: 6px; overflow: hidden; color: var(--absence-meta); font-size: 12px; font-weight: 500; white-space: nowrap; }
        .absence-event-kind { overflow: hidden; text-overflow: ellipsis; }
        .absence-event-dates { flex-shrink: 0; }
        .absence-event-continuation { flex-shrink: 0; color: var(--absence-accent); font-size: 12px; }
        @container (max-width: 230px) {
            .absence-event-dates { display: none; }
        }
        @container (max-width: 839px) {
            .absence-calendar .fc-header-toolbar { position: sticky; left: 0; width: 100cqw; flex-wrap: wrap; gap: 12px; }
            .absence-calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(2) { order: -1; width: 100%; text-align: center; }
        }
        @media (max-width: 1023px) {
            .absence-calendar-scroll-hint { display: block; margin-bottom: 12px; color: #475569; font-size: 12px; }
            .dark .absence-calendar-scroll-hint { color: #cbd5e1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .absence-calendar .absence-event { transition: none; }
            .absence-calendar-views::before, .absence-calendar-views button { transition: none; }
        }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('gestorVacaciones', () => ({
                modalOpen: @json($errors->any() && !old('absence_id')),
                editModalOpen: @json($errors->any() && (bool) old('absence_id')),
                fechaSeleccionada: @json(old('fecha', '')),
                
                editData: {
                    id: @json(old('absence_id', '')),
                    nombre: '',
                    fecha: @json(old('fecha', '')),
                    fecha_fin: @json(old('fecha_fin', '')),
                    tipo: @json(old('tipo', '')),
                    motivo: @json(old('motivo_empleado', '')),
                    version: @json(old('version', ''))
                },
                
                abrirModal(fecha) {
                    this.fechaSeleccionada = fecha;
                    this.modalOpen = true;
                },

                abrirEditModal(data) {
                    this.editData = { ...data };
                    this.editModalOpen = true;
                }
            }));
        });

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var eventosBD = @json($eventos);

            // These dates are inclusive in the absence record. FullCalendar's end is exclusive.
            function absencePresentation(event) {
                var props = event.extendedProps;
                var start = props.fecha || event.startStr.slice(0, 10);
                var finish = props.fecha_fin || start;
                var days = Math.round((Date.parse(finish + 'T00:00:00Z') - Date.parse(start + 'T00:00:00Z')) / 86400000) + 1;
                var dateFormat = new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'short' });
                var dates = dateFormat.formatRange(new Date(start + 'T12:00:00'), new Date(finish + 'T12:00:00'));
                var kind = props.historical ? 'Bloqueo histórico' : ({ vacaciones: 'Vacaciones', dia_libre: 'Día libre', baja: 'Baja' }[props.tipo] || 'Ausencia');
                var pending = props.estado === 'pendiente';
                var name = props.nombre || event.title.split(' - ')[0];
                return {
                    name: name,
                    kind: kind + (pending ? ' · Pendiente' : ''),
                    dates: dates,
                    duration: days === 1 ? '1 día' : days + ' días',
                    tone: props.historical ? 'historical' : pending ? 'pending' : ({ vacaciones: 'vacation', dia_libre: 'free', baja: 'sick' }[props.tipo] || 'historical'),
                    status: props.estado === 'aprobada' ? 'Aprobada' : ''
                };
            }

            function absenceText(className, text) {
                var element = document.createElement('span');
                element.className = className;
                element.textContent = text;
                return element;
            }

            var calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'es',
                dayHeaderDidMount: window.mmqpCalendarAccessibility.dayHeaderDidMount,
                dayCellDidMount: function(info) {
                    if (info.isToday) {
                        var number = info.el.querySelector('.fc-daygrid-day-number');
                        number?.setAttribute('aria-current', 'date');
                        number?.setAttribute('title', 'Hoy');
                    }
                },
                viewDidMount: window.mmqpCalendarAccessibility.viewDidMount,
                initialView: 'dayGridMonth',
                headerToolbar: false,
                datesSet: function(info) {
                    document.getElementById('absence-calendar-title').textContent = info.view.title;
                    var current = info.view.calendar.getDate();
                    document.getElementById('absence-month-picker').value = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0');
                    document.getElementById('absence-month-view').setAttribute('aria-pressed', String(info.view.type === 'dayGridMonth'));
                    document.getElementById('absence-week-view').setAttribute('aria-pressed', String(info.view.type === 'dayGridWeek'));
                    calendarEl.querySelector('.fc-view-harness')?.setAttribute('aria-labelledby', 'absence-calendar-title');
                    calendarEl.querySelector('.fc-view-harness')?.setAttribute('role', 'group');
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana'
                },
                events: eventosBD,
                height: 'auto',
                eventClassNames: ['absence-event'],
                eventContent: function(info) {
                    var data = absencePresentation(info.event);
                    var content = document.createElement('div');
                    content.className = 'absence-event-content';
                    var heading = document.createElement('div');
                    heading.className = 'absence-event-heading';
                    if (!info.isStart) heading.append(absenceText('absence-event-continuation', '↳'));
                    heading.append(absenceText('absence-event-name', data.name), absenceText('absence-event-duration', data.duration));
                    var meta = document.createElement('div');
                    meta.className = 'absence-event-meta';
                    meta.append(absenceText('absence-event-kind', data.kind), absenceText('absence-event-dates', '· ' + data.dates));
                    if (!info.isEnd) heading.append(absenceText('absence-event-continuation', '→'));
                    content.append(heading, meta);
                    return { domNodes: [content] };
                },
                eventDidMount: function(info) {
                    var data = absencePresentation(info.event);
                    var label = [data.name, data.kind, data.dates, data.duration, data.status].filter(Boolean).join(' · ');
                    info.el.dataset.absenceTone = data.tone;
                    info.el.title = label;
                    info.el.setAttribute('aria-label', label);
                },
                dateClick: function(info) {
                    // Abrir modal enviando la fecha clicada
                    window.dispatchEvent(new CustomEvent('abrir-modal-fecha', { detail: info.dateStr }));
                },
                eventClick: function(info) {
                    // Abrir modal de edición
                    var props = info.event.extendedProps;
                    if (props.historical) {
                        alert('Este bloqueo conserva información histórica. Administración debe revisar su origen antes de retirarlo; no es una solicitud aprobada.');
                        return;
                    }
                    window.dispatchEvent(new CustomEvent('abrir-modal-editar', {
                        detail: {
                            id: info.event.id,
                            nombre: props.nombre,
                            fecha: props.fecha,
                            fecha_fin: props.fecha_fin,
                            tipo: props.tipo,
                            motivo: props.motivo,
                            version: props.version
                        }
                    }));
                }
            });

            calendar.render();
            document.getElementById('absence-prev').addEventListener('click', function() { calendar.prev(); });
            document.getElementById('absence-next').addEventListener('click', function() { calendar.next(); });
            document.getElementById('absence-month-view').addEventListener('click', function() { calendar.changeView('dayGridMonth'); });
            document.getElementById('absence-week-view').addEventListener('click', function() { calendar.changeView('dayGridWeek'); });
            document.getElementById('absence-month-picker').addEventListener('change', function() {
                if (/^\d{4}-\d{2}$/.test(this.value)) calendar.gotoDate(this.value + '-01');
            });
        });
    </script>
    @endpush
</x-app-layout>
