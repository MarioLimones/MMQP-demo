<div x-show="isModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="schedule-day-design-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        
        <!-- Background overlay with blur -->
        <div x-show="isModalOpen" @click="closeModal()" 
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <!-- Modal panel -->
        <div x-show="isModalOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-flex flex-col align-bottom text-left shadow-2xl transition-all w-full relative max-w-full h-screen rounded-none m-0 border-none"
             :class="isPreviewing ? 'bg-white dark:bg-slate-800' : 'bg-slate-50 dark:bg-slate-900'">
             
             <!-- Patrón de rayas sutil de fondo para preview -->
             <div x-show="isPreviewing" class="absolute inset-0 pointer-events-none opacity-[0.03] dark:opacity-[0.05] z-0" 
                  style="background-image: repeating-linear-gradient(-45deg, currentColor, currentColor 2px, transparent 2px, transparent 12px);"></div>
            
            <div class="p-4 sm:px-6 sm:py-4 flex-1 flex flex-col min-h-0 text-slate-900 dark:text-slate-100">
                <div class="sm:flex sm:items-start flex-1 flex flex-col min-h-0">
                    
                    <!-- Banner de Previsualización -->
                    <div x-show="isPreviewing" style="display: none;" class="w-full bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl mb-4 p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-800/50 flex items-center justify-center">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-red-900 dark:text-red-300 font-bold text-lg">Modo Previsualización</h4>
                                <p class="text-red-700 dark:text-red-400 text-sm">Estás viendo la plantilla <strong x-text="previewPlantillaName"></strong>. Los cambios no se guardarán hasta que la apliques.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <button @click="cancelPreview()" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-bold transition-colors">
                                Cancelar
                            </button>
                            <button @click="applyPreview()" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white shadow-md shadow-red-500/30 rounded-lg text-sm font-bold transition-all hover:scale-105">
                                Aplicar Plantilla
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-center sm:mt-0 sm:text-left w-full flex-1 flex flex-col min-h-0">
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3 mb-3">
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-4">
                                    <button @click="cambiarDia(-1)" class="p-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-full text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border border-slate-200 dark:border-slate-700 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                    </button>
                                    <h3 class="text-xl font-extrabold text-slate-900 dark:text-white w-56 text-center" id="schedule-day-design-title">
                                        <span x-text="diaSeleccionado" class="capitalize text-red-600 dark:text-red-400"></span>
                                        <span class="text-base text-slate-500 dark:text-slate-400 font-medium ml-2" x-text="fechasDias[diaSeleccionado]"></span>
                                    </h3>
                                    <button @click="cambiarDia(1)" class="p-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-full text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors border border-slate-200 dark:border-slate-700 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </button>
                                </div>
                                
                                <!-- Barra de herramientas -->
                                <div class="flex items-center gap-6 border-l border-slate-200 dark:border-white/10 pl-6">
                                    <!-- Botones de Acción Globales -->
                                    <div class="flex items-center gap-2" x-show="!isPreviewing">
                                        <!-- Cargar Plantilla -->
                                        <button @click="loadPlantillas()" class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-emerald-900/30 text-slate-700 dark:text-slate-300 hover:text-emerald-700 dark:hover:text-emerald-400 rounded-lg font-bold text-xs transition-colors border border-slate-200 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-500/50 shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                            Cargar Plantilla
                                        </button>
                                        
                                        <!-- Guardar Plantilla (Día) -->
                                        <button @click="isSaveModalOpen = true" class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-blue-900/30 text-slate-700 dark:text-slate-300 hover:text-blue-700 dark:hover:text-blue-400 rounded-lg font-bold text-xs transition-colors border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-500/50 shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                            Guardar Plantilla
                                        </button>
                                        
                                        <!-- Indicador Guardado -->
                                        <div class="flex items-center gap-2 mr-4" x-show="autoSaveState" x-transition>
                                            <span class="text-[11px] font-bold uppercase tracking-wider" 
                                                  :class="{
                                                      'text-green-600 dark:text-green-400': autoSaveState === 'Guardado',
                                                      'text-blue-600 dark:text-blue-400 animate-pulse': autoSaveState === 'Guardando...',
                                                      'text-red-600 dark:text-red-400': autoSaveState === 'Error'
                                                  }" 
                                                  x-text="autoSaveState">
                                            </span>
                                            <svg x-show="autoSaveState === 'Guardado'" class="w-3.5 h-3.5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                            <svg x-show="autoSaveState === 'Guardando...'" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                        
                                        <!-- Botón Notas -->
                                        <button @click="isNotesModalOpen = true" class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-amber-900/30 text-slate-700 dark:text-slate-300 hover:text-amber-700 dark:hover:text-amber-400 rounded-lg font-bold text-xs transition-colors border border-slate-200 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-500/50 shadow-sm relative"
                                                :class="notas[diaSeleccionado]?.trim() !== '' ? 'ring-1 ring-amber-500/50' : ''">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Notas
                                            <span x-show="notas[diaSeleccionado]?.trim() !== ''" class="absolute -top-1 -right-1 w-3 h-3 bg-amber-500 rounded-full border-2 border-white dark:border-slate-900 shadow-sm animate-pulse"></span>
                                        </button>
                                    </div>
                                    
                                    <!-- Separador -->
                                    <div class="w-px h-8 bg-slate-200 dark:bg-slate-700/50" x-show="!isPreviewing"></div>
                                    
                                    <!-- Botón Volver (Cerrar) -->
                                    <button x-show="!isPreviewing" @click="closeModal()" class="w-10 h-10 flex items-center justify-center rounded-full bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-white hover:bg-red-50 dark:hover:bg-red-500 transition-all ml-2 border border-slate-200 dark:border-slate-700 hover:border-red-200 dark:hover:border-red-400 hover:scale-110 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-3 mt-1 flex-1 min-h-0">
                            
                            <!-- Vista Vertical del Día (Ancho Completo, 4 Columnas) -->
                            <div class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col overflow-hidden relative shadow-sm" @mouseleave="endDrag()" @mouseup="endDrag()">
                                
                                <div class="flex-1 relative overflow-auto custom-scrollbar" x-ref="gridContainer">
                                    <div class="flex h-full">
                                        
                                        <!-- Panel lateral de Empleados -->
                                        <div class="w-40 flex-none bg-slate-50 dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex flex-col z-50 sticky left-0 shadow-[4px_0_15px_rgba(0,0,0,0.05)] dark:shadow-[4px_0_15px_rgba(0,0,0,0.3)]">
                                            <div class="h-10 shrink-0 border-b border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/90 flex items-center justify-center sticky top-0 z-20">
                                                <span class="text-xs uppercase tracking-widest font-black text-slate-500 dark:text-slate-300">Empleados</span>
                                            </div>
                                            <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-4">
                                                <template x-for="rol in roles" :key="'sb-rol-'+rol">
                                                    <div>
                                                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2 border-b border-slate-200 dark:border-slate-700 pb-1" x-text="rol"></div>
                                                        <div class="space-y-2">
                                                            <template x-for="emp in empleados.filter(e => e.rol === rol)" :key="'sb-emp-'+emp.id">
                                                                <div class="p-2 rounded-lg border flex flex-col cursor-grab active:cursor-grabbing hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shadow-sm"
                                                                     :class="{
                                                                        'opacity-50 grayscale border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/50': isEmpleadoAusente(emp.id, diaSeleccionado) || emp.en_baja,
                                                                        'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-500 hover:shadow-md': !isEmpleadoAusente(emp.id, diaSeleccionado) && !emp.en_baja
                                                                     }"
                                                                     draggable="true"
                                                                     @dragstart="startEmpleadoDrag($event, emp)"
                                                                >
                                                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate" x-text="`${emp.nombre} ${emp.apellidos || ''}`"></span>
                                                                    
                                                                    <div class="flex justify-between items-center mt-1 bg-slate-50 dark:bg-slate-900/50 rounded px-1.5 py-0.5 border border-slate-100 dark:border-transparent">
                                                                        <span class="text-[9px] text-slate-500 font-medium">Horas mes:</span>
                                                                        <span class="text-[10px] font-bold" 
                                                                              :class="{
                                                                                  'text-red-600 dark:text-rose-400': getHorasMes(emp.id) > emp.horas_mensuales,
                                                                                  'text-emerald-600 dark:text-emerald-400': getHorasMes(emp.id) == emp.horas_mensuales,
                                                                                  'text-amber-600 dark:text-amber-400': getHorasMes(emp.id) < emp.horas_mensuales
                                                                              }"
                                                                              x-text="`${getHorasMes(emp.id)}/${emp.horas_mensuales}`">
                                                                        </span>
                                                                    </div>
                                                                    
                                                                    <template x-if="emp.en_baja">
                                                                        <span class="text-[9px] text-red-600 dark:text-rose-500 font-bold mt-1 text-center bg-red-50 dark:bg-transparent rounded">BAJA MÉDICA</span>
                                                                    </template>
                                                                    <template x-if="!emp.en_baja && isEmpleadoAusente(emp.id, diaSeleccionado)">
                                                                        <span class="text-[9px] text-amber-600 dark:text-rose-400 font-bold mt-1 text-center bg-amber-50 dark:bg-transparent rounded">Vacaciones/Libre</span>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        <!-- Eje Y: Horas -->
                                        <div class="w-16 flex-none bg-slate-50 dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex flex-col z-40 sticky left-40 shadow-[4px_0_15px_rgba(0,0,0,0.02)] dark:shadow-[4px_0_15px_rgba(0,0,0,0.3)]">
                                            <div class="h-10 shrink-0 border-b border-transparent"></div>
                                            <div class="flex-1 flex flex-col relative">
                                                <template x-for="hora in horas" :key="'vlbl-'+hora">
                                                    <div class="text-right relative border-b border-transparent pr-2 flex-1 min-h-0">
                                                        <span class="text-sm font-black text-slate-500 dark:text-slate-400 absolute -top-2.5 right-2" x-text="hora"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        <!-- Grid de Roles (4 Columnas) -->
                                        <div class="flex-1 flex bg-white dark:bg-slate-900 relative divide-x divide-slate-100 dark:divide-slate-800">
                                            <!-- Líneas horizontales de fondo -->
                                            <div class="absolute inset-0 flex flex-col pointer-events-none z-0">
                                                <div class="h-10 shrink-0 border-b border-transparent"></div>
                                                <div class="flex-1 flex flex-col" x-ref="gridInner">
                                                    <template x-for="hora in horas" :key="'vline-'+hora">
                                                        <div class="border-t border-slate-100 dark:border-slate-800 w-full flex-1 min-h-0"></div>
                                                    </template>
                                                </div>
                                            </div>
                                            
                                            <!-- Columna por cada Rol -->
                                            <template x-for="rol in roles" :key="'vcol-'+rol">
                                                <div class="flex-1 border-r border-slate-100 dark:border-slate-800 flex flex-col relative min-h-0">
                                                    <!-- Cabecera de Columna (Sticky) -->
                                                    <div class="h-10 shrink-0 flex items-center justify-center bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm border-b border-slate-200 dark:border-slate-700 z-30 sticky top-0 shadow-sm dark:shadow-none">
                                                        <div class="w-2.5 h-2.5 rounded-full mr-2.5" 
                                                             :class="{
                                                                 'bg-rose-500': rol === 'encargado',
                                                                 'bg-blue-500': rol === 'repartidor',
                                                                 'bg-amber-500': rol === 'auxiliar',
                                                                 'bg-emerald-500': rol === 'limpiadora'
                                                             }"></div>
                                                        <span class="text-xs uppercase tracking-widest font-black text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                                            <span x-text="rol"></span>
                                                            <span class="opacity-90 font-bold text-[10px] bg-slate-100 dark:bg-slate-900/60 px-2 py-0.5 rounded text-slate-500 dark:text-slate-400" x-text="`${getPuestosUtilizadosCount(diaSeleccionado, rol)}/${getEmpleadosTotalCount(diaSeleccionado, rol)} ACTIVOS`"></span>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Área Scrolleable Horizontalmente -->
                                                    <div class="flex-1 flex flex-col overflow-x-auto overflow-y-hidden relative z-10 custom-scrollbar"
                                                         @mousemove="updateLinkingMouse($event, $el)">
                                                         
                                                        <!-- Spacer para forzar el scroll horizontal si hay múltiples columnas calculadas -->
                                                        <div :style="`width: ${getCardMetrics(diaSeleccionado, rol).scrollWidth}px; min-width: 100%; height: 1px;`"></div>
                                                        
                                                        <!-- Visual Links SVG Layer -->
                                                        <svg class="absolute inset-0 w-full h-full pointer-events-none z-30 overflow-visible" style="min-width: 100%;" x-html="getVisualLinksHtml(diaSeleccionado, rol)">
                                                        </svg>
                                                        
                                                        <!-- Bloques de Arrastre Vertical (Interacción) -->
                                                        <div class="absolute inset-0 flex flex-col z-0 pointer-events-auto">
                                                            <template x-for="bloque in bloques" :key="'vdrag-'+rol+'-'+bloque.minuto">
                                                                <div class="flex-1 cursor-crosshair hover:bg-slate-100 dark:hover:bg-slate-700/20 transition-colors w-full"
                                                                     @mousedown.prevent.stop="startDrag(rol, bloque.minuto)"
                                                                     @mouseenter="onDrag(rol, bloque.minuto)">
                                                                </div>
                                                            </template>
                                                        </div>

                                                        <!-- Drag Preview (Previsualización Vertical) -->
                                                        <template x-if="isDragging && dragRole === rol">
                                                            <div class="absolute left-0 border-y-2 border-x flex flex-col items-center justify-center shadow-lg transition-all duration-75 z-20 pointer-events-none rounded-lg"
                                                                 :style="getTurnoStyle({hora_inicio: minToTime(Math.min(dragStartMin, dragCurrentMin)), hora_fin: minToTime(Math.max(dragStartMin, dragCurrentMin) + 30)}) + ` width: max(100%, ${getCardMetrics(diaSeleccionado, rol).scrollWidth}px);`"
                                                                 :class="{
                                                                    'bg-rose-50/80 dark:bg-rose-900/30 border-rose-300 dark:border-rose-500 text-rose-700 dark:text-rose-300': rol === 'encargado',
                                                                    'bg-blue-50/80 dark:bg-blue-900/30 border-blue-300 dark:border-blue-500 text-blue-700 dark:text-blue-300': rol === 'repartidor',
                                                                    'bg-amber-50/80 dark:bg-amber-900/30 border-amber-300 dark:border-amber-500 text-amber-700 dark:text-amber-300': rol === 'auxiliar',
                                                                    'bg-emerald-50/80 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-500 text-emerald-700 dark:text-emerald-300': rol === 'limpiadora'
                                                                 }">
                                                                 <span class="font-black text-xs sm:text-sm bg-white/90 dark:bg-slate-900/90 px-2.5 py-1 rounded-md shadow-sm border border-black/5 dark:border-white/10" x-text="minToTime(Math.min(dragStartMin, dragCurrentMin)) + ' - ' + minToTime(Math.max(dragStartMin, dragCurrentMin) + 30)"></span>
                                                            </div>
                                                        </template>
                                                        
                                                        <!-- Turnos de este Rol -->
                                                        <template x-for="turno in getTurnosPorRol(diaSeleccionado, rol)" :key="'v-'+turno.id">
                                                            <div :style="getTurnoStyle(turno) + ` width: ${getCardMetrics(diaSeleccionado, rol).width - 6}px; left: ${8 + getColumnForTurno(turno, diaSeleccionado) * getCardMetrics(diaSeleccionado, rol).width}px; z-index: ${activeDragId === turno.id ? 100 : 10 + getColumnForTurno(turno, diaSeleccionado)};`" 
                                                                 class="absolute rounded-xl border flex flex-col transition-all duration-200 group hover:!z-50 hover:shadow-lg hover:scale-[1.02] shadow-sm overflow-hidden"
                                                                 :class="{
                                                                     'bg-rose-50 dark:bg-rose-900/40 border-rose-200 dark:border-rose-700/50 text-rose-800 dark:text-rose-200': turno.rol === 'encargado',
                                                                     'bg-blue-50 dark:bg-blue-900/40 border-blue-200 dark:border-blue-700/50 text-blue-800 dark:text-blue-200': turno.rol === 'repartidor',
                                                                     'bg-amber-50 dark:bg-amber-900/40 border-amber-200 dark:border-amber-700/50 text-amber-800 dark:text-amber-200': turno.rol === 'auxiliar',
                                                                     'bg-emerald-50 dark:bg-emerald-900/40 border-emerald-200 dark:border-emerald-700/50 text-emerald-800 dark:text-emerald-200': turno.rol === 'limpiadora',
                                                                     'cursor-move shadow-xl !z-[100] ring-2 ring-indigo-400 scale-[1.02]': activeDragId === turno.id,
                                                                     'ring-2 ring-red-400 ring-dashed scale-[1.05] shadow-red-500/30 cursor-crosshair': isLinking && linkingSourceTurnoId !== turno.id && getTurnoById(linkingSourceTurnoId)?.rol === turno.rol,
                                                                     'cursor-pointer': activeDragId !== turno.id && !isLinking
                                                                 }"
                                                                 @mousedown.stop.prevent="initBlockDrag($event, turno, 'move')"
                                                                 @mouseup="if(isLinking) finishLinking(turno)"
                                                                 @dragover.prevent="$event.dataTransfer.dropEffect = 'copy'"
                                                                 @drop.prevent="asignarEmpleadoDrop($event, turno)">
                                                                
                                                                <!-- Agarradera Superior -->
                                                                <div class="absolute top-0 left-0 w-full h-2 cursor-ns-resize z-50 hover:bg-black/10 dark:hover:bg-white/10 transition-colors" @mousedown.stop.prevent="initBlockDrag($event, turno, 'resize-top')"></div>
                                                                
                                                                <!-- Botón de Romper Enlace -->
                                                                <button x-show="isLinked(turno)" @click.stop="romperEnlace(turno)" title="Romper enlace" class="absolute top-1.5 left-1.5 opacity-60 hover:opacity-100 transition-opacity bg-white/90 dark:bg-slate-800/90 text-orange-600 dark:text-orange-400 hover:!bg-orange-500 hover:!text-white rounded-md p-1 shadow-sm z-[110] border border-black/5 dark:border-white/10 pointer-events-auto">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                                        <line x1="4" y1="4" x2="20" y2="20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></line>
                                                                    </svg>
                                                                </button>
                                                                
                                                                <!-- Botón de Borrar -->
                                                                <button @click.stop="eliminarTurno(diaSeleccionado, turno.id)" title="Eliminar turno" class="absolute top-1.5 right-1.5 opacity-40 hover:opacity-100 transition-opacity bg-white/90 dark:bg-slate-800/90 text-red-600 dark:text-red-400 hover:!bg-red-500 hover:!text-white rounded-md p-1 shadow-sm z-[110] border border-black/5 dark:border-white/10 pointer-events-auto">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                                </button>
                                                                
                                                                <!-- Contenido Centrado -->
                                                                <div class="flex-1 flex flex-col items-center justify-center pointer-events-none w-full px-2 text-center mt-2">
                                                                    <div class="text-[12px] sm:text-[13px] font-black uppercase tracking-widest leading-tight mb-1 break-words px-4">
                                                                        <span x-text="turno.empleado_id ? getEmpleadoNombre(turno.empleado_id) : (turno.rol + ' ' + (turno.puesto_num || 1))"></span>
                                                                    </div>
                                                                    <div class="font-bold text-[11px] sm:text-xs tracking-tight flex items-center opacity-80 bg-white/50 dark:bg-black/20 px-2 py-0.5 rounded-full border border-black/5 dark:border-white/5">
                                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                        <span x-text="turno.hora_inicio + ' - ' + turno.hora_fin"></span>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Botón de Asignar (Hover) -->
                                                                <button @click.stop="abrirAsignacion(turno)" title="Asignar empleado" class="absolute bottom-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:!text-emerald-600 dark:hover:!text-emerald-400 rounded-md p-1.5 shadow-sm border border-slate-200 dark:border-slate-600 z-40 text-[10px] font-black flex items-center gap-1 uppercase tracking-wider">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                                    <span x-text="turno.empleado_id ? 'Cambiar' : 'Asignar'"></span>
                                                                </button>
                                                                
                                                                <!-- Enlace Handle (Punto Inferior) -->
                                                                <div class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-4 h-4 bg-white dark:bg-slate-700 rounded-full border-2 border-slate-300 dark:border-slate-500 shadow-sm cursor-pointer opacity-0 group-hover:opacity-100 transition-all z-50 hover:scale-125 hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900"
                                                                     @mousedown.stop.prevent="startLinking(turno)">
                                                                </div>
                                                                
                                                                <!-- Agarradera Inferior -->
                                                                <div class="absolute bottom-0 left-0 w-full h-2 cursor-ns-resize z-30 hover:bg-black/10 dark:hover:bg-white/10 transition-colors" @mousedown.stop.prevent="initBlockDrag($event, turno, 'resize-bottom')"></div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
