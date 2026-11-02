<x-app-layout>

    <div class="py-12 bg-slate-50 dark:bg-slate-900 dark:[color-scheme:dark] min-h-screen" x-data='horariosDesigner({{ $empleados->toJson() }}, {{ $ausencias->toJson() }}, @json($festivos))' @mousemove.window="onBlockDrag($event)" @mouseup.window="endBlockDrag($event); endDrag(); if(isLinking) isLinking=false;">
        <div class="w-full px-4 mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xl shadow-slate-200/50 dark:shadow-black/20 sm:rounded-3xl border border-slate-100 dark:border-slate-700 mb-6">
                <div class="p-8 sm:p-12 text-slate-900 dark:text-slate-100">
                    
                    <!-- Encabezado y Selector de Semana -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6">
                        <div>
                            <h3 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-red-600 dark:from-red-400 to-blue-500 dark:to-blue-400 mb-2">
                                Planificación Semanal
                            </h3>
                        </div>
                        <div class="flex flex-col xl:flex-row gap-4 xl:items-center w-full md:w-auto mt-4 md:mt-0">
                            
                            <!-- Grupo de Filtros / Ajustes -->
                            <div class="flex flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-900 p-2 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative z-20">
                                <div class="flex items-center gap-2">
                                    <input type="week" x-model="semana" :disabled="isLoadingDraft || isGenerating" aria-label="Semana del borrador" class="border-0 bg-white dark:bg-slate-800 ring-1 ring-slate-200 dark:ring-slate-600 focus:ring-2 focus:ring-indigo-500 rounded-xl shadow-sm p-2.5 w-44 text-slate-700 dark:text-slate-200 font-medium text-sm transition-all duration-200 cursor-pointer" title="Semana del Año">
                                    <div class="hidden sm:flex items-center bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-500/30 px-2.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap shadow-sm" x-show="semana" x-cloak>
                                        <span x-text="(fechasDias['lunes'] || '') + ' - ' + (fechasDias['domingo'] || '')"></span>
                                    </div>
                                </div>
                                <div class="w-px h-8 bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>
                                <select x-model="tiempoGeneracion" class="border-0 bg-white dark:bg-slate-800 ring-1 ring-slate-200 dark:ring-slate-600 focus:ring-2 focus:ring-indigo-500 rounded-xl shadow-sm p-2.5 w-28 text-slate-700 dark:text-slate-200 font-medium text-sm transition-all duration-200 cursor-pointer" title="Tiempo límite de IA">
                                    <option value="1">1 min</option>
                                    <option value="2">2 min</option>
                                    <option value="5">5 min</option>
                                    <option value="10">10 min</option>
                                    <option value="15">15 min</option>
                                </select>
                            </div>

                            <!-- Grupo de Inteligencia Artificial -->
                            <div class="flex items-center gap-2">
                                <button type="button" @click="abrirAutoPlantilla()" :disabled="isGeneratingTemplate || isLoadingDraft || isGenerating" aria-haspopup="dialog" class="bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold py-2.5 px-4 rounded-xl border border-emerald-200 dark:border-emerald-500/40 shadow-sm transition-all text-sm flex items-center justify-center gap-2 relative disabled:opacity-50 disabled:cursor-wait">
                                    <span>Auto-Plantilla</span>
                                </button>
                                
                                <div class="flex items-center bg-purple-50 dark:bg-purple-500/10 rounded-xl border border-purple-200 dark:border-purple-500/40 shadow-sm p-0.5">
                                    <button @click="cargarEventosLocales()" class="hover:bg-purple-100 dark:hover:bg-purple-500/20 text-purple-700 dark:text-purple-300 font-bold py-2 px-3 rounded-lg transition-all text-sm flex items-center justify-center gap-2 relative">
                                        <svg x-show="isCargandoEventos" class="animate-spin -ml-1 h-4 w-4 absolute" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span :class="{'opacity-0': isCargandoEventos}">Predictor Demanda</span>
                                    </button>
                                    <div class="w-px h-5 bg-purple-200 dark:bg-purple-500/30 mx-1"></div>
                                    <button @click="showSettingsModal = true" class="hover:bg-purple-100 dark:hover:bg-purple-500/20 text-purple-700 dark:text-purple-300 p-2 rounded-lg transition-all" title="Ajustes de IA">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Botón Principal -->
                            <button @click="generarHorario()" class="inline-flex items-center justify-center px-6 py-2.5 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 rounded-xl font-bold text-sm tracking-wide hover:bg-slate-800 dark:hover:bg-slate-200 transition-all duration-300 shadow-md hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-500">
                                <svg x-show="isGenerating" class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="isGenerating ? 'Generando...' : 'Generar Horario'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="mb-6 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-4 text-sm">
                        <p role="status" aria-live="polite" x-text="autoSaveState"></p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">Los cambios de este borrador solo serán visibles para el empleado cuando los publiques explícitamente.</p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            <button type="button" @click="verBorrador()" :disabled="isLoadingDraft || isGenerating || !!draftError" class="rounded-lg bg-blue-600 px-4 py-3 font-bold text-white disabled:opacity-50">Revisar y publicar</button>
                            <button type="button" x-show="draftError" @click="triggerAutoSave()" :disabled="draftSession?.blocked" class="rounded-lg border px-4 py-3">Reintentar guardado</button>
                            <button type="button" x-show="draftError" @click="descargarCambios()" class="rounded-lg border px-4 py-3">Descargar mis cambios</button>
                            <button type="button" x-show="draftError" @click="window.location.reload()" class="rounded-lg border px-4 py-3">Recargar para revisar</button>
                        </div>
                        <p x-show="draftError" role="alert" class="mt-3 font-semibold text-red-700 dark:text-red-300" x-text="draftError"></p>
                    </div>
                    <div :inert="isLoadingDraft || isGenerating || !!draftError">
                    <!-- Botones de Días -->
                    <div class="mb-4 mt-6">
                        <span class="text-sm font-bold text-slate-600 dark:text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                            Selecciona un día para añadir turnos
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-16 relative">
                        <!-- Línea decorativa de fondo -->
                        <div class="absolute inset-x-0 top-1/2 h-px bg-slate-200 dark:bg-slate-700 -z-10 hidden lg:block"></div>
                        
                        <template x-for="dia in dias" :key="dia">
                            <button @click="openModal(dia)" 
                                    class="group relative bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 pt-6 flex flex-col items-center justify-center font-semibold text-slate-700 dark:text-slate-200 hover:text-red-600 dark:hover:text-red-300 hover:border-red-300 dark:hover:border-red-400/60 shadow-sm hover:shadow-xl hover:shadow-red-500/10 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-red-500/20 h-40">
                                
                                <!-- Círculo decorativo -->
                                <div class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-900 group-hover:bg-red-50 dark:group-hover:bg-red-500/20 flex items-center justify-center mb-2 transition-colors duration-300">
                                    <svg class="w-5 h-5 text-slate-400 group-hover:text-red-500 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                
                                <span x-text="dia" class="text-base capitalize tracking-wide"></span>
                                <span x-text="fechasDias[dia]" class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 font-medium tracking-wider uppercase"></span>
                                
                                <!-- Badge dinámico animado -->
                                <div class="absolute -top-2 -right-2 transform scale-0 group-hover:scale-100 transition-transform duration-300 origin-center" x-show="turnos[dia].length > 0" x-cloak>
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white shadow-md ring-2 ring-white dark:ring-slate-800">
                                        <span x-text="turnos[dia].length"></span>
                                    </span>
                                </div>
                                
                                <!-- Espacio reservado para el badge estático para no solapar la fecha -->
                                <div class="h-6 mt-2 flex items-center justify-center">
                                    <!-- Badge estático si no hay hover -->
                                    <div class="text-[10px] font-bold text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-500/10 px-2.5 py-1 rounded-full uppercase tracking-wider group-hover:opacity-0 transition-opacity duration-300" x-show="turnos[dia].length > 0" x-cloak>
                                        <span x-text="turnos[dia].length"></span> turnos
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- Calendario Preview -->
                    <section class="schedule-preview" aria-labelledby="schedule-preview-title" aria-busy="true" :aria-busy="isLoadingDraft ? 'true' : 'false'">
                        <div class="schedule-preview__loading" x-show="isLoadingDraft" x-cloak aria-hidden="true"
                             x-data="{
                                 stopTracking: null,
                                 init() {
                                     const overlay = this.$el;
                                     const indicator = overlay.firstElementChild;
                                     let frame = null;
                                     const update = () => {
                                         if (frame !== null) return;
                                         frame = requestAnimationFrame(() => {
                                             frame = null;
                                             const rect = overlay.getBoundingClientRect();
                                             const top = Math.max(0, rect.top);
                                             const bottom = Math.min(window.innerHeight, rect.bottom);
                                             const position = Math.max(60, Math.min(rect.height - 60, (top + bottom) / 2 - rect.top));
                                             indicator.style.transform = 'translate3d(0, ' + position + 'px, 0) translateY(-50%)';
                                         });
                                     };
                                     const stop = () => {
                                         window.removeEventListener('scroll', update);
                                         window.removeEventListener('resize', update);
                                         if (frame !== null) cancelAnimationFrame(frame);
                                         frame = null;
                                     };
                                     const track = loading => {
                                         stop();
                                         if (!loading) return;
                                         window.addEventListener('scroll', update, { passive: true });
                                         window.addEventListener('resize', update);
                                         this.$nextTick(() => { if (isLoadingDraft) update(); });
                                     };
                                     this.stopTracking = stop;
                                     this.$watch('isLoadingDraft', track);
                                     track(isLoadingDraft);
                                 },
                                 destroy() { this.stopTracking?.(); }
                             }">
                            <div class="schedule-preview__loading-indicator">
                                <span class="schedule-preview__spinner"></span>
                                <span>Cargando calendario…</span>
                            </div>
                        </div>
                        <div class="schedule-preview__heading">
                            <div class="schedule-preview__icon bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-300">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            </div>
                            <h3 id="schedule-preview-title">Calendario semanal</h3>
                        </div>
                        
                        <div class="schedule-preview__content">
                            
                            <!-- Selector de Pestañas (Roles) -->
                            <div class="schedule-preview__roles" role="group" aria-label="Filtrar calendario por puesto">
                                <template x-for="r in roles" :key="'tab-'+r">
                                    <button type="button" @click="previewRol = r"
                                            class="schedule-preview__role" :aria-pressed="previewRol === r"
                                            aria-controls="schedule-preview-calendar">
                                        <span class="w-2 h-2 rounded-full shrink-0" aria-hidden="true"
                                             :class="{
                                                 'bg-rose-500': r === 'encargado',
                                                 'bg-red-500': r === 'repartidor',
                                                 'bg-amber-400': r === 'auxiliar',
                                                 'bg-emerald-500': r === 'limpiadora'
                                             }"></span>
                                        <span x-text="r"></span>
                                    </button>
                                </template>
                            </div>
                            
                            <!-- Calendario del Rol Seleccionado -->
                            <div id="schedule-preview-calendar" class="schedule-preview-calendar" tabindex="0" role="region" :aria-label="'Calendario semanal: ' + previewRol">
                                <!-- Eje Y: Horas -->
                                <div class="schedule-preview__hours" aria-hidden="true">
                                    <template x-for="hora in horas" :key="'lbl-'+hora">
                                        <div class="schedule-preview__hour">
                                            <span x-text="hora"></span>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- Eje X: Días (Grid) -->
                                <div class="schedule-preview__days" :style="getPreviewGridStyle()">
                                    <template x-for="dia in dias" :key="'cal-'+dia">
                                        <div class="schedule-preview__day">
                                            <!-- Header Día -->
                                            <div class="schedule-preview__day-heading">
                                                <span x-text="dia"></span>
                                                <span class="schedule-preview__date" x-text="fechasDias[dia]"></span>
                                            </div>
                                            
                                            <!-- Timeline Día -->
                                            <div class="schedule-preview__timeline">
                                                <!-- Líneas horizontales de fondo -->
                                                <div class="absolute inset-0 flex flex-col pointer-events-none">
                                                    <template x-for="hora in horas.slice(0, -1)" :key="'line-'+hora+'-'+dia">
                                                        <div class="schedule-preview__line"></div>
                                                    </template>
                                                </div>
                                                
                                                <!-- Turnos del Rol Seleccionado en el Día -->
                                                <div class="absolute inset-0">
                                                    <template x-for="turno in getTurnosPorRol(dia, previewRol)" :key="turno.id">
                                                        <div :style="getPreviewTurnoStyle(turno, dia)"
                                                             class="schedule-preview-shift" :data-role="turno.rol"
                                                             :data-stacked="getPreviewLayout(dia, turno.rol).some(entry => entry.turno.id === turno.id && entry.columns > 1 && entry.end - entry.start >= 90)"
                                                             :data-short="timeToMinutes(turno.hora_fin) - timeToMinutes(turno.hora_inicio) < 90 && timeToMinutes(turno.hora_fin) > timeToMinutes(turno.hora_inicio)"
                                                             role="group" :aria-label="dia + ', ' + turno.rol + ', puesto ' + turno.puesto_num + ', ' + turno.hora_inicio + ' a ' + turno.hora_fin"
                                                             :title="dia + ' · Puesto ' + turno.puesto_num + ' · ' + turno.hora_inicio + ' – ' + turno.hora_fin">
                                                            
                                                            <button type="button" @click.stop="eliminarTurno(dia, turno.id)" :aria-label="'Eliminar turno del ' + dia + ', puesto ' + turno.puesto_num + ', de ' + turno.hora_inicio + ' a ' + turno.hora_fin" title="Eliminar turno" class="schedule-preview__delete">
                                                                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                            
                                                            <div class="schedule-preview__time">
                                                                <span x-text="turno.hora_inicio"></span><span class="schedule-preview__separator" aria-hidden="true">–</span><span x-text="turno.hora_fin"></span>
                                                            </div>
                                                            <div class="schedule-preview__position" x-show="turno.puesto_num" :title="'Puesto ' + turno.puesto_num">
                                                                <span x-text="turno.puesto_num"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </div>

        <!-- Modal Diseño Día (Glassmorphism) -->
        <x-horarios.modal-diseno-dia />

        
        

        

        
    
            <!-- Modales -->
            <x-horarios.modal-asignacion />
            <x-horarios.modal-notas />
            <x-horarios.modal-save-plantilla />
            <x-horarios.modal-load-plantilla />
            <x-horarios.modal-confirmacion />
            <x-horarios.modal-auto-plantilla />
            <x-horarios.modal-ajuste-manual />




            <!-- OVERLAY DE CARGA -->
            <div x-cloak x-show="isGenerating" class="fixed inset-0 z-[200] flex flex-col items-center justify-center bg-slate-900/70 backdrop-blur-xl transition-all duration-300">
                <div class="bg-white dark:bg-slate-800 p-10 rounded-3xl shadow-2xl flex flex-col items-center max-w-md w-full mx-4 border border-red-200 dark:border-red-500/40">
                    <div class="relative w-24 h-24 mb-8">
                        <div class="absolute inset-0 border-8 border-slate-100 dark:border-slate-700 rounded-full"></div>
                        <div class="absolute inset-0 border-8 border-red-600 rounded-full border-t-transparent animate-spin"></div>
                        <svg class="absolute inset-0 m-auto w-10 h-10 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <h3 class="text-2xl font-black text-slate-800 dark:text-slate-100 text-center mb-3">Generando y Validando...</h3>
                    <p class="text-base text-slate-600 dark:text-slate-300 text-center font-medium">Se está procesando miles de combinaciones para cuadrar los turnos.</p>
                    <div class="mt-6 bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 px-4 py-3 rounded-xl border border-amber-200 dark:border-amber-500/40 text-sm font-bold flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Esto puede tardar unos minutos.</span>
                        </div>
                        <div class="flex items-center gap-2 text-amber-900 dark:text-amber-300 bg-amber-100/50 dark:bg-amber-500/20 p-2 rounded-lg justify-center">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Tiempo transcurrido: <span class="font-mono text-base ml-1" x-text="Math.floor(generationTimeElapsed / 60).toString().padStart(2, '0') + ':' + (generationTimeElapsed % 60).toString().padStart(2, '0')">00:00</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Eventos Locales -->
            <!-- Modal Predictor de Demanda (IA) -->
            <x-horarios.modal-predictor />

            <!-- Modal Ajustes IA -->
            <div x-show="showSettingsModal" class="fixed inset-0 z-[60] overflow-y-auto" x-cloak>
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="showSettingsModal" 
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 transition-opacity" 
                         @click="showSettingsModal = false" aria-hidden="true">
                        <div class="absolute inset-0 bg-slate-900 opacity-75 backdrop-blur-sm"></div>
                    </div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div x-show="showSettingsModal"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl shadow-slate-900/50 transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100 dark:border-slate-700">
                        
                        <div class="bg-slate-50 dark:bg-slate-900 px-6 py-4 flex justify-between items-center border-b border-slate-100 dark:border-slate-700">
                            <h3 class="text-lg leading-6 font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                <svg class="w-5 h-5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                Ajustes de IA
                            </h3>
                            <button @click="showSettingsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors focus:outline-none">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <div class="px-6 py-6 bg-white dark:bg-slate-800 space-y-6">
                            <div>
                                <p class="text-sm text-slate-700 dark:text-slate-200">Los eventos de esta demo son ejemplos ficticios. No se requieren claves ni se consulta ningún servicio externo.</p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-slate-500">Ubicación ficticia: Ciudad Demo.</p>
                            </div>
                            
                            <div>
                                
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 dark:bg-slate-900 px-6 py-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700">
                            <button @click="showSettingsModal = false" class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                Cancelar
                            </button>
                            <button @click="guardarAjustesIA()" class="px-5 py-2.5 bg-slate-800 dark:bg-indigo-600 hover:bg-slate-900 dark:hover:bg-indigo-500 text-white rounded-xl font-bold text-sm shadow-sm transition-all transform hover:-translate-y-0.5">
                                Entendido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- El script JS ha sido extraído a un archivo separado -->
    <script src="{{ asset('js/schedule-draft-session.js') }}"></script>
    <script src="{{ asset('js/horarios-designer.js') }}?v={{ time() }}"></script>




    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
