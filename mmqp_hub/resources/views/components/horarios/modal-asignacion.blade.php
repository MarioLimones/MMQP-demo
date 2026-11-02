<!-- Modal Asignación de Empleado -->
        <div x-show="isAssignModalOpen" style="display: none;" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="schedule-employee-assignment-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                <div x-show="isAssignModalOpen" @click="cerrarAsignacion()" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div x-show="isAssignModalOpen" 
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                     class="inline-block align-bottom bg-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border border-slate-700">
                    
                    <div class="px-6 py-5 border-b border-slate-700 bg-slate-800 flex justify-between items-center">
                        <h3 id="schedule-employee-assignment-title" class="text-lg font-black text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Asignar Empleado
                        </h3>
                        <button @click="cerrarAsignacion()" class="text-slate-400 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="px-6 py-4 bg-slate-900/50">
                        <p class="text-sm text-slate-400 mb-4">
                            Selecciona un empleado para el puesto <span class="font-bold text-white uppercase" x-text="assignTurnoRol + ' ' + assignTurnoPuesto"></span>.
                            <br><span class="text-xs text-red-300">Esto asignará al empleado a todos los turnos vinculados a este puesto en el día de hoy.</span>
                        </p>
                        
                        <div class="bg-red-500/20 p-2 mb-2 text-xs text-white">
                            Debug Paula: <span x-text="JSON.stringify(empleados.find(e => e.nombre && e.nombre.includes('Paula')))"></span>
                        </div>
                        
                        <div class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar pr-2">
                            <template x-for="emp in getEmpleadosDisponibles(assignTurnoRol).filter(e => e.activo !== false && e.activo !== 0 && e.activo !== '0' && e.en_baja !== true && e.en_baja !== 1)" :key="'emp-'+emp.id">
                                <button @click="confirmarAsignacion(emp.id)" 
                                        class="w-full text-left px-4 py-3 rounded-xl border border-slate-700 bg-slate-800 hover:bg-red-600 hover:border-red-500 transition-all flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-700 group-hover:bg-red-500 flex items-center justify-center text-white font-bold text-xs" x-text="emp.nombre.charAt(0) + (emp.apellidos ? emp.apellidos.charAt(0) : '')"></div>
                                        <div>
                                            <div class="text-sm font-bold text-white" x-text="emp.nombre + (emp.apellidos ? ' ' + emp.apellidos : '')"></div>
                                            <div class="text-xs text-slate-400 group-hover:text-red-200" x-text="emp.rol"></div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <span class="text-xs font-black text-slate-300 group-hover:text-white bg-slate-900 group-hover:bg-red-700 px-2 py-1 rounded-md" x-text="emp.horas_mensuales + 'h'"></span>
                                    </div>
                                </button>
                            </template>
                            
                            <div x-show="getEmpleadosDisponibles(assignTurnoRol).filter(e => e.activo !== false && e.activo !== 0 && e.activo !== '0' && e.en_baja !== true && e.en_baja !== 1).length === 0" class="text-center py-6">
                                <svg class="w-12 h-12 text-slate-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                <p class="text-slate-400 text-sm">No hay empleados disponibles con este rol.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-slate-700 bg-slate-800 flex justify-between items-center">
                        <button @click="confirmarAsignacion(null)" class="text-sm font-bold text-rose-400 hover:text-rose-300 transition-colors flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            Desasignar
                        </button>
                        <button @click="cerrarAsignacion()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-bold transition-colors">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>