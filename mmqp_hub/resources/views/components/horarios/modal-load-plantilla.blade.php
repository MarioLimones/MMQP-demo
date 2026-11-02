<!-- Modal Cargar Plantilla (Full Screen) -->
        <div x-show="isLoadModalOpen" style="display: none;" class="fixed inset-0 z-[100] overflow-hidden flex flex-col bg-slate-900" aria-labelledby="schedule-template-library-title" role="dialog" aria-modal="true">
            
            <!-- Encabezado del modal -->
            <div class="px-6 py-4 border-b border-slate-700 bg-slate-800 flex justify-between items-center shrink-0">
                <h3 id="schedule-template-library-title" class="text-xl font-black text-white flex items-center gap-3">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    Gestor de Plantillas
                </h3>
                <button @click="isLoadModalOpen = false" class="text-slate-400 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-full p-2 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="flex flex-1 min-h-0 overflow-hidden" x-data="{ tabActiva: 'guardadas' }">
                
                <!-- Barra lateral de navegación -->
                <div class="w-64 bg-slate-800 border-r border-slate-700 shrink-0 flex flex-col">
                    <button @click="tabActiva = 'guardadas'" :class="tabActiva === 'guardadas' ? 'bg-emerald-500/20 text-emerald-400 border-r-4 border-emerald-500' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-200'" class="px-6 py-5 text-left font-bold text-sm transition-all flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                        Plantillas Guardadas
                    </button>
                    <button @click="tabActiva = 'pasadas'" :class="tabActiva === 'pasadas' ? 'bg-emerald-500/20 text-emerald-400 border-r-4 border-emerald-500' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-200'" class="px-6 py-5 text-left font-bold text-sm transition-all flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Días Anteriores
                    </button>
                </div>
                
                <!-- Contenido principal -->
                <div class="flex-1 bg-slate-900/50 overflow-y-auto custom-scrollbar p-8">
                    
                    <!-- TAB: Plantillas Guardadas -->
                    <div x-show="tabActiva === 'guardadas'">
                        <h2 class="text-2xl font-bold text-white mb-6">Mis Plantillas Guardadas</h2>
                        
                        <div x-show="plantillasGuardadas.length === 0" class="text-center py-12 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            <p class="text-slate-400 font-medium text-lg">No tienes ninguna plantilla guardada.</p>
                            <p class="text-slate-500 text-sm mt-2">Puedes guardar tu diseño actual usando el botón "Guardar" del panel principal.</p>
                        </div>
                        
                        <div x-show="plantillasGuardadas.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            <template x-for="plantilla in plantillasGuardadas" :key="'pl-'+plantilla.id">
                                <div class="p-5 rounded-2xl border border-slate-700 bg-slate-800 hover:border-emerald-500/50 transition-colors group flex flex-col shadow-lg relative overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
                                    <h4 class="text-lg font-bold text-white truncate mb-1 mt-1" x-text="plantilla.nombre"></h4>
                                    <p class="text-sm text-slate-400 mb-6" x-text="plantilla.turnos.length + ' turnos configurados'"></p>
                                    
                                    <div class="mt-auto grid grid-cols-2 gap-2">
                                        <button @click="previsualizarPlantilla(plantilla.turnos, plantilla.nombre)" class="px-3 py-2 bg-slate-700/50 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Ver
                                        </button>
                                        <button @click="applyPlantilla(plantilla.turnos)" class="px-3 py-2 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-500 hover:text-white rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                            Cargar
                                        </button>
                                    </div>
                                    <button @click.stop="deletePlantilla(plantilla.id)" title="Borrar plantilla" class="absolute top-4 right-4 p-1.5 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- TAB: Días Anteriores (Historial) -->
                    <div x-show="tabActiva === 'pasadas'" style="display: none;">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-white">Historial Anual</h2>
                            <p class="text-sm text-slate-400">Navega por el historial desplegando el año y mes deseado.</p>
                        </div>
                        
                        <div x-show="Object.keys(horariosPasadosAgrupados).length === 0" class="text-center py-12 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p class="text-slate-400 font-medium text-lg">No hay horarios guardados en el sistema.</p>
                        </div>
                        
                        <div class="space-y-4">
                            <template x-for="(mesesObj, anyo) in horariosPasadosAgrupados" :key="anyo">
                                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
                                    <!-- Cabecera del Año -->
                                    <button @click="toggleYear(anyo)" class="w-full px-6 py-4 border-b border-slate-700 bg-slate-800/80 flex items-center justify-between hover:bg-slate-700/50 transition-colors focus:outline-none">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <h3 class="font-bold text-white text-lg" x-text="'Año ' + anyo"></h3>
                                        </div>
                                        <svg class="w-5 h-5 text-slate-400 transform transition-transform" :class="{'rotate-180': expandedYears[anyo]}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    
                                    <!-- Contenido del Año (Meses) -->
                                    <div x-show="expandedYears[anyo]" x-transition style="display: none;" class="p-2 sm:p-4 bg-slate-900/30 space-y-3">
                                        <template x-for="(semanas, mes) in mesesObj" :key="mes">
                                            <div class="bg-slate-800/80 rounded-xl border border-slate-700/50 overflow-hidden">
                                                <!-- Cabecera del Mes -->
                                                <button @click="toggleMonth(anyo, mes)" class="w-full px-5 py-3 border-b border-slate-700/50 bg-slate-800/50 flex items-center justify-between hover:bg-slate-700/80 transition-colors focus:outline-none">
                                                    <div class="flex items-center gap-2">
                                                        <h4 class="font-bold text-slate-200" x-text="mes"></h4>
                                                        <span class="bg-slate-700 text-slate-300 text-xs px-2 py-0.5 rounded-full" x-text="semanas.length + ' sem.'"></span>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-400 transform transition-transform" :class="{'rotate-180': expandedMonths[anyo+'-'+mes]}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                </button>
                                                
                                                <!-- Contenido del Mes (Semanas) -->
                                                <div x-show="expandedMonths[anyo+'-'+mes]" x-transition style="display: none;" class="p-3 space-y-3">
                                                    <template x-for="semana in semanas" :key="'sem-'+semana.id">
                                                        <div class="bg-slate-800 rounded-lg border border-slate-700 p-3 shadow-sm hover:border-slate-500 transition-colors">
                                                            <div class="flex justify-between items-center mb-3">
                                                                <div class="font-bold text-white text-sm" x-text="formatearFechaSemana(semana.fecha_inicio, semana.fecha_fin)"></div>
                                                            </div>
                                                            <!-- Grid de Días de la semana -->
                                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2">
                                                                <template x-for="diaNombre in ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo']" :key="diaNombre">
                                                                    <div x-show="extraerTurnosDeSemana(semana, diaNombre).length > 0" class="bg-slate-900 border border-slate-700 rounded-lg p-2 flex flex-col">
                                                                        <div class="text-xs font-bold text-slate-300 capitalize mb-1" x-text="diaNombre"></div>
                                                                        <div class="text-[10px] text-slate-500 mb-2" x-text="extraerTurnosDeSemana(semana, diaNombre).length + ' turnos'"></div>
                                                                        <div class="mt-auto grid grid-cols-1 gap-1">
                                                                            <button @click="previsualizarPlantilla(extraerTurnosDeSemana(semana, diaNombre), diaNombre + ' (' + formatearFechaSemana(semana.fecha_inicio, semana.fecha_fin) + ')')" class="w-full py-1 bg-slate-700/50 text-slate-300 hover:bg-slate-700 hover:text-white rounded text-[10px] font-bold transition-colors">Ver</button>
                                                                            <button @click="applyPlantilla(extraerTurnosDeSemana(semana, diaNombre))" class="w-full py-1 bg-red-600/20 text-red-400 hover:bg-red-500 hover:text-white rounded text-[10px] font-bold transition-colors">Cargar</button>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
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

        <!-- Modal Confirmación de Carga de Plantilla -->
        <div x-show="isConfirmLoadOpen" style="display: none;" class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto overflow-x-hidden bg-slate-900/60 " aria-labelledby="schedule-template-load-confirm-title" role="dialog" aria-modal="true">
            <div class="relative w-full max-w-md p-4 h-auto">
                <div class="relative bg-slate-800 rounded-2xl shadow-lg border border-slate-700 p-6 sm:p-8 overflow-hidden" @click.stop>
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500 to-emerald-500"></div>
                    
                    <div class="flex items-center justify-center w-16 h-16 rounded-full bg-red-500/10 text-red-400 mx-auto mb-6">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                    </div>
                    
                    <h3 id="schedule-template-load-confirm-title" class="text-xl font-bold text-white text-center mb-2">¿Cómo deseas cargar la plantilla?</h3>
                    <p class="text-slate-400 text-center text-sm mb-8">
                        Puedes cargar la plantilla con los empleados que ya tenía asignados, o cargar solo los turnos vacíos (sin asignaciones).
                    </p>
                    
                    <div class="space-y-3">
                        <button @click="confirmarCargaPlantilla(true)" class="w-full flex items-center justify-between p-4 bg-slate-700/50 hover:bg-slate-700 border border-slate-600 hover:border-emerald-500/50 rounded-xl transition-all group">
                            <div class="text-left">
                                <div class="text-white font-bold text-sm">Con Asignaciones</div>
                                <div class="text-slate-400 text-xs mt-0.5">Mantiene los empleados asignados.</div>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                        </button>
                        
                        <button @click="confirmarCargaPlantilla(false)" class="w-full flex items-center justify-between p-4 bg-slate-700/50 hover:bg-slate-700 border border-slate-600 hover:border-red-500/50 rounded-xl transition-all group">
                            <div class="text-left">
                                <div class="text-white font-bold text-sm">Sin Asignaciones</div>
                                <div class="text-slate-400 text-xs mt-0.5">Carga los turnos vacíos.</div>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        </button>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t border-slate-700 flex justify-center">
                        <button @click="cancelarCargaPlantilla()" class="px-6 py-2.5 bg-transparent hover:bg-slate-700 text-slate-300 font-bold text-sm rounded-lg transition-colors border border-slate-600 hover:border-slate-500">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
