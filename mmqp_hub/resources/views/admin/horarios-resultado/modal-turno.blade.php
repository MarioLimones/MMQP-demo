    <div x-show="showTurnoModal" style="display: none;" class="[color-scheme:light] dark:[color-scheme:dark] fixed inset-0 z-[100] flex items-center justify-center print:hidden">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showTurnoModal = false"></div>
        
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden relative z-10" @click.stop>
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white" x-text="turnoForm.id ? 'Editar Turno' : 'Añadir Turno'"></h3>
                <button @click="showTurnoModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div x-show="turnoFormError" class="bg-red-50 text-red-600 text-sm p-3 rounded-lg font-medium" x-text="turnoFormError" style="display:none;"></div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider mb-1">Día</label>
                        <input type="text" x-model="turnoForm.dia" disabled class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 rounded-xl text-sm font-bold cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider mb-1">Rol</label>
                        <input type="text" x-model="turnoForm.rol" disabled class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 rounded-xl text-sm font-bold cursor-not-allowed uppercase">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider mb-1">Empleado</label>
                    <select x-model="turnoForm.empleado_id" class="w-full bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 border-slate-200 dark:border-slate-600 focus:ring-red-500 focus:border-red-500 rounded-xl text-sm font-medium disabled:bg-slate-100 dark:disabled:bg-slate-900 dark:disabled:text-slate-400" x-bind:disabled="turnoForm.id !== null">
                        <template x-for="emp in getEmpleadosConTurno(turnoForm.rol)" :key="emp.id">
                            <option class="bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100" x-bind:value="emp.id" x-text="emp.nombre"></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider mb-1">Hora Inicio</label>
                        <input type="time" x-model="turnoForm.hora_inicio" class="w-full bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 border-slate-200 dark:border-slate-600 focus:ring-red-500 focus:border-red-500 rounded-xl text-sm font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider mb-1">Hora Fin</label>
                        <input type="time" x-model="turnoForm.hora_fin" class="w-full bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 border-slate-200 dark:border-slate-600 focus:ring-red-500 focus:border-red-500 rounded-xl text-sm font-bold">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
                <button @click="showTurnoModal = false" type="button" class="px-4 py-2 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                    Cancelar
                </button>
                <button @click="guardarTurnoModal()" type="button" class="px-4 py-2 text-sm font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 shadow-sm transition-colors">
                    Guardar
                </button>
            </div>
        </div>
    </div>
