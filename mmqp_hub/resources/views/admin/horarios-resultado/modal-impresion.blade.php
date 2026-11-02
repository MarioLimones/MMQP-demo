    <style>
        /* Estilos Específicos para la Impresión */
        @media print {
            header, nav, footer { display: none !important; }
            body { background: white !important; margin: 0 !important; padding: 0 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { size: A4 portrait; margin: 0mm; }
        }
    </style>

    <!-- MODAL DE IMPRESIÓN -->
    <div x-show="modoImpresion" style="display: none;" class="fixed inset-0 z-[100] bg-slate-200 flex overflow-hidden print:bg-white print:z-auto print:relative print:inset-auto print:block print:w-full">
        <!-- Sidebar de Controles (Solo pantalla) -->
        <div class="w-80 bg-white border-r border-slate-300 shadow-2xl flex flex-col print:hidden h-full">
            <div class="p-6 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                <h3 class="font-extrabold text-lg text-slate-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Ajustar Impresión
                </h3>
                <button @click="modoImpresion = false" class="text-slate-400 hover:text-red-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-6 space-y-8">
                <div>
                    <label class="flex items-center gap-2 text-sm text-slate-700 bg-slate-100 border border-slate-200 px-3 py-2 rounded-lg cursor-pointer">
                        <input type="checkbox" x-model="ocultarHoras" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                        <span class="font-bold">Ocultar horas totales</span>
                    </label>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-slate-700">Hojas A4</h4>
                        <div class="flex bg-slate-100 rounded-lg p-1 border border-slate-200">
                            <button @click="if(numHojas > 1) numHojas--" class="px-2 py-0.5 hover:bg-white rounded text-slate-600 shadow-sm font-bold">-</button>
                            <span class="px-3 text-sm font-bold text-slate-800" x-text="numHojas"></span>
                            <button @click="numHojas++" class="px-2 py-0.5 hover:bg-white rounded text-slate-600 shadow-sm font-bold">+</button>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <h4 class="font-bold text-slate-700 border-b pb-2">Ajustes por Rol</h4>
                    <template x-for="rol in rolesDisponibles" :key="rol">
                        <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-3">
                            <h5 class="font-extrabold text-sm uppercase text-red-800" x-text="rol"></h5>
                            
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1 block">Hoja Destino</label>
                                <select x-model="configImpresion[rol].hoja" class="w-full text-sm rounded-lg border-slate-300 focus:border-red-500 focus:ring-red-500">
                                    <template x-for="i in numHojas">
                                        <option x-bind:value="i" x-text="'Hoja ' + i"></option>
                                    </template>
                                </select>
                            </div>
                            
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Escala (Zoom)</label>
                                    <span class="text-xs font-bold text-slate-700" x-text="configImpresion[rol].escala + '%'"></span>
                                </div>
                                <input type="range" x-model="configImpresion[rol].escala" min="30" max="150" step="5" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-red-600">
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="p-6 border-t border-slate-200 bg-slate-50">
                <button onclick="window.print()" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl shadow-md transition-all text-sm flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Imprimir Ahora
                </button>
            </div>
        </div>

        <!-- Área de Previsualización (Fondo gris en pantalla, transparente en impresión) -->
        <div class="flex-1 overflow-auto bg-slate-200 p-8 flex flex-col gap-8 items-center print:p-0 print:bg-transparent print:block relative w-full">
            <template x-for="hoja in numHojas" :key="hoja">
                <div class="bg-white shadow-2xl shrink-0 print:shadow-none print:break-after-page relative print:p-0"
                     style="width: 210mm; min-height: 297mm; padding: 10mm; box-sizing: border-box;">
                    
                    <div class="absolute top-4 right-8 text-xs font-bold text-slate-300 print:hidden" x-text="'HOJA ' + hoja"></div>

                    <!-- En cada hoja, renderizamos los roles que le pertenecen -->
                    <template x-for="rol in rolesDisponibles" :key="rol">
                        <template x-if="parseInt(configImpresion[rol].hoja) === parseInt(hoja)">
                            <div class="mb-8 print:mb-4" x-bind:style="'zoom: ' + (configImpresion[rol].escala/100) + ';'">
                                <div class="bg-amber-400 px-6 py-2 flex items-center gap-3 border-b border-amber-500 rounded-t-xl print:border-none print:bg-amber-100">
                                    <h3 class="text-lg font-extrabold text-slate-900" x-text="rol"></h3>
                                </div>
                                <div class="border border-slate-200 rounded-b-xl overflow-hidden bg-white">
                                    <x-tabla-horario />
                                </div>
                            </div>
                        </template>
                    </template>
                </div>
            </template>
        </div>
    </div>
