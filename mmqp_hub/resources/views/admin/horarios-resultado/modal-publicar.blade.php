<!-- Modal Publicar Horario -->
<div x-show="showPublicarModal" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center print:hidden">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showPublicarModal = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden relative z-10" @click.stop
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex justify-between items-center">
            <h3 class="text-xl font-black text-slate-800 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Publicar Horario
            </h3>
            <button @click="showPublicarModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 bg-white dark:bg-slate-700 rounded-full p-1 transition-colors shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div class="p-6">
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">
                Se guardarán primero los cambios actuales y se publicará esa versión completa. Selecciona los grupos que podrán verla. Sin grupos seleccionados, se retirará la publicación vigente.
            </p>

            <form @submit.prevent="publicarResultado()" action="{{ isset($id) ? route('admin.horarios.publicar', $id) : '#' }}" method="POST" id="formPublicarHorario">
                @csrf
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 cursor-pointer hover:bg-blue-100 transition-colors">
                        <input type="checkbox" @change="seleccionarTodosRoles($event.target.checked)" class="w-5 h-5 rounded border-blue-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                        <span class="font-bold text-blue-900 dark:text-blue-300">Todos los empleados</span>
                    </label>

                    <div class="h-px bg-slate-200 dark:bg-slate-700 my-4"></div>

                    <template x-for="rol in rolesPublicables" :key="rol">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
                            <input type="checkbox" name="roles[]" :value="rol" x-model="rolesSeleccionados" class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            <span class="font-semibold text-slate-700 dark:text-slate-300 capitalize" x-text="rol"></span>
                        </label>
                    </template>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
            <button @click="showPublicarModal = false" type="button" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 shadow-sm transition-colors">
                Cancelar
            </button>
            <button @click="publicarResultado()" :disabled="isPublishing || !!draftError" type="button" class="px-5 py-2.5 text-sm font-bold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-md transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Confirmar Publicación
            </button>
        </div>
    </div>
</div>
