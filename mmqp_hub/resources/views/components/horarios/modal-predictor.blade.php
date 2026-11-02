<!-- Modal Predictor de Demanda (IA) -->
<div x-show="showEventosModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay de fondo -->
        <div x-show="showEventosModal" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity" 
             @click="showEventosModal = false" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900 opacity-75 backdrop-blur-sm"></div>
        </div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Contenido del modal -->
        <div x-show="showEventosModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl shadow-purple-900/50 transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-purple-100 dark:border-purple-500/30">
            
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-xl leading-6 font-bold text-white flex items-center gap-2">
                    Predictor de Demanda (IA)
                </h3>
                <button @click="showEventosModal = false" class="text-purple-200 hover:text-white transition-colors focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="px-6 pt-5 pb-6 bg-slate-50 dark:bg-slate-900">
                <div class="mb-6 flex gap-4 items-end justify-between">
                    <div class="text-sm font-medium text-slate-600 dark:text-slate-300">
                        Buscando eventos en: <strong class="text-purple-700 dark:text-purple-300" x-text="direccionPostal"></strong>
                    </div>
                    <button @click="cargarEventosLocales()" class="bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-500/30 font-bold py-2 px-4 rounded-xl border border-transparent transition-all shadow-sm flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Actualizar
                    </button>
                </div>
                
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 min-h-[150px] shadow-sm">
                    <template x-if="isCargandoEventos">
                        <div class="flex flex-col items-center justify-center h-full py-8 text-slate-400">
                            <svg class="animate-spin h-8 w-8 text-purple-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <p class="font-medium animate-pulse">Analizando eventos de la semana con IA...</p>
                        </div>
                    </template>
                    
                    <template x-if="!isCargandoEventos && eventosLocalesHTML">
                        <div class="prose prose-sm prose-purple max-w-none prose-li:marker:text-purple-500" x-html="eventosLocalesHTML">
                        </div>
                    </template>
                    
                    <template x-if="!isCargandoEventos && !eventosLocalesHTML">
                        <div class="text-center text-slate-500 dark:text-slate-400 py-8">
                            Haz clic en actualizar para consultar los eventos.
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
