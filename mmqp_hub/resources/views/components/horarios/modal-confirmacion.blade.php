<!-- Modal Confirmación Exceso/Déficit -->
<div x-show="showConfirmModal" style="display: none;" class="fixed inset-0 z-[110] overflow-y-auto" aria-labelledby="schedule-generation-confirmation-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="showConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="showConfirmModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100 dark:border-slate-700">
            <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 dark:bg-amber-500/20 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-bold text-slate-900 dark:text-slate-100" id="schedule-generation-confirmation-title">
                            Atención Requerida
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4" x-text="confirmData.message"></p>
                            
                            <!-- Detalles Exceso -->
                            <template x-if="confirmData.exceso && Object.keys(confirmData.exceso).length > 0">
                                <div class="mb-3 p-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/30 rounded-lg">
                                    <h4 class="text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider mb-2">Excesos Detectados</h4>
                                    <ul class="text-xs text-rose-600 dark:text-rose-300 space-y-1 list-disc list-inside">
                                        <template x-for="(horas, rol) in confirmData.exceso">
                                            <li>
                                                <span class="font-semibold" x-text="rol + ':'"></span>
                                                <span x-text="horas + ' horas de sobra'"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                            
                            <!-- Detalles Déficit -->
                            <template x-if="confirmData.deficit && Object.keys(confirmData.deficit).length > 0">
                                <div class="p-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/30 rounded-lg">
                                    <h4 class="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider mb-2">Déficits Detectados</h4>
                                    <ul class="text-xs text-amber-700 dark:text-amber-300 space-y-1 list-disc list-inside">
                                        <template x-for="(horas, rol) in confirmData.deficit">
                                            <li>
                                                <span class="font-semibold" x-text="rol + ':'"></span>
                                                <span x-text="horas + ' horas'"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse rounded-b-2xl border-t border-slate-100 dark:border-slate-700">
                <button type="button" @click="showConfirmModal = false; generarHorario(true)" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-amber-500 text-base font-bold text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:ring-offset-slate-800 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                    Generar de todos modos
                </button>
                <button type="button" @click="showConfirmModal = false; abrirAjusteManual()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:ring-offset-slate-800 focus:ring-red-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                    Ajustar Manualmente
                </button>
                <button type="button" @click="showConfirmModal = false" class="mt-3 w-full inline-flex justify-center rounded-xl border border-slate-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:ring-offset-slate-800 focus:ring-red-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
