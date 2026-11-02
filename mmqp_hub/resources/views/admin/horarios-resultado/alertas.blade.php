            <!-- TURNOS NO CUBIERTOS -->
            <template x-if="turnosNoCubiertos.length > 0">
                <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-r-2xl p-6 mb-10 shadow-sm print:hidden">
                    <div class="flex items-center gap-3 mb-3">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <h3 class="text-lg font-bold text-red-800 dark:text-red-400">Turnos Sin Cubrir (<span x-text="turnosNoCubiertos.length"></span>)</h3>
                    </div>
                    <p class="text-red-700 dark:text-red-300 text-sm mb-4">El algoritmo no ha podido asignar empleados a los siguientes turnos. Puedes asignarlos manualmente añadiéndolos en las tablas de abajo.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <template x-for="t in turnosNoCubiertos" :key="t.turno_id">
                            <div class="bg-white dark:bg-slate-800 border border-red-200 dark:border-red-800 rounded-xl p-4 shadow-sm flex flex-col">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-xs font-bold px-2 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded-md uppercase" x-text="t.dia"></span>
                                    <span class="text-xs font-bold px-2 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-md uppercase" x-text="t.rol"></span>
                                </div>
                                <div class="text-lg font-black text-slate-800 dark:text-slate-100 mb-1" x-text="t.hora_inicio + ' - ' + t.hora_fin"></div>
                                <div class="text-sm text-slate-500 dark:text-slate-400 font-medium" x-text="t.duracion_horas + ' hrs'"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
