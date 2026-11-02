<!-- Modal Ajuste Manual de Horas -->
<div x-show="isAjusteManualOpen" style="display: none;" class="schedule-adjustment fixed inset-0 z-[120] overflow-y-auto" aria-labelledby="schedule-adjustment-title" role="dialog" aria-modal="true">
    <div class="schedule-adjustment__positioner">
        
        <!-- Opaque scrim avoids filtering the entire planner during interaction. -->
        <div x-show="isAjusteManualOpen" @click="isAjusteManualOpen = false" 
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="schedule-adjustment__backdrop fixed inset-0 transition-opacity" aria-hidden="true"></div>

        <!-- Modal Content -->
        <div x-show="isAjusteManualOpen" 
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-[0.99]"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-[0.99]"
             class="schedule-adjustment__panel bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            
            <div class="schedule-adjustment__header border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-4">
                    <div class="schedule-adjustment__icon bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-300">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    </div>
                    <div>
                        <h3 id="schedule-adjustment-title" class="schedule-adjustment__title text-slate-800 dark:text-slate-100">
                            Ajuste Manual de Horas
                        </h3>
                        <p class="schedule-adjustment__subtitle text-slate-500 dark:text-slate-400">Introduce las horas semanales o pulsa Autocompletar. El contrato se muestra como referencia.</p>
                    </div>
                </div>
                <button type="button" aria-label="Cerrar ajuste manual" @click="isAjusteManualOpen = false" class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-900 hover:bg-rose-50 dark:hover:bg-rose-500/20 text-slate-400 hover:text-rose-500 flex items-center justify-center schedule-adjustment__control focus:outline-none focus:ring-2 focus:ring-rose-500/50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="schedule-adjustment__body bg-slate-50 dark:bg-slate-900">
                <!-- Sidebar con Roles -->
                <div class="schedule-adjustment__roles bg-white dark:bg-slate-800 border-slate-100 dark:border-slate-700" role="group" aria-label="Puesto que ajustar">
                    <template x-for="r in roles" :key="'ajuste-rol-'+r">
                        <button type="button" @click="ajusteRole = r" :aria-pressed="ajusteRole === r"
                                class="schedule-adjustment__role schedule-adjustment__control"
                                :class="ajusteRole === r ? 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/40' : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-700'">
                            
                            <span class="schedule-adjustment__role-name"
                                  :class="ajusteRole === r ? 'text-red-700 dark:text-red-300' : 'text-slate-600 dark:text-slate-300'"
                                  x-text="r"></span>
                            
                            <!-- Badges de aviso -->
                            <div class="relative z-10 flex gap-1">
                                <template x-if="confirmData.deficit && confirmData.deficit[r]">
                                    <span class="bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 text-[10px]">Faltan <span x-text="confirmData.deficit[r]"></span>h</span>
                                </template>
                                <template x-if="confirmData.exceso && confirmData.exceso[r]">
                                    <span class="bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 text-[10px]">Sobran <span x-text="confirmData.exceso[r]"></span>h</span>
                                </template>
                                <template x-if="(!confirmData.deficit || !confirmData.deficit[r]) && (!confirmData.exceso || !confirmData.exceso[r])">
                                    <span class="bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-[10px] flex items-center">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>

                <!-- Contenido Principal -->
                <div class="schedule-adjustment__content custom-scrollbar">
                    
                    <div class="schedule-adjustment__summary">
                        <div class="schedule-adjustment__summary-layout">
                            <div class="min-w-0">
                                <h4 class="text-lg font-bold text-slate-800 dark:text-slate-100 capitalize mb-2">
                                    <span x-text="ajusteRole"></span>
                                </h4>
                                <div class="schedule-adjustment__totals text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500 dark:text-slate-400 font-medium">Requeridas:</span>
                                        <span class="font-black text-slate-700 dark:text-slate-200 text-base" x-text="(confirmData.requeridas[ajusteRole] || 0) + 'h'"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500 dark:text-slate-400 font-medium">Disponibles:</span>
                                        <span class="font-black text-slate-700 dark:text-slate-200 text-base" x-text="(confirmData.disponibles[ajusteRole] || 0) + 'h'"></span>
                                    </div>
                                </div>
                                <div class="schedule-adjustment__status inline-flex items-center gap-2 text-sm font-semibold"
                                     :class="{
                                         'text-amber-700 dark:text-amber-300': confirmData.deficit && confirmData.deficit[ajusteRole],
                                         'text-rose-700 dark:text-rose-300': confirmData.exceso && confirmData.exceso[ajusteRole],
                                         'text-emerald-700 dark:text-emerald-300': (!confirmData.deficit || !confirmData.deficit[ajusteRole]) && (!confirmData.exceso || !confirmData.exceso[ajusteRole])
                                     }">
                                    <template x-if="confirmData.deficit && confirmData.deficit[ajusteRole]">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span>Faltan <span x-text="confirmData.deficit[ajusteRole]"></span> horas para cubrir la demanda</span>
                                        </div>
                                    </template>
                                    <template x-if="confirmData.exceso && confirmData.exceso[ajusteRole]">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span>Sobran <span x-text="confirmData.exceso[ajusteRole]"></span> horas en los contratos</span>
                                        </div>
                                    </template>
                                    <template x-if="(!confirmData.deficit || !confirmData.deficit[ajusteRole]) && (!confirmData.exceso || !confirmData.exceso[ajusteRole])">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span>Horas cuadradas al milímetro</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <button type="button" @click="autoBalancearRol(ajusteRole)" class="schedule-adjustment__balance schedule-adjustment__control bg-white dark:bg-slate-800 border border-red-200 dark:border-red-500/40 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-700 dark:text-red-300">
                                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                <span>Autocompletar</span>
                            </button>
                        </div>
                    </div>

                    <div class="schedule-adjustment__employees">
                        <template x-for="emp in getEmpleadosAjuste(ajusteRole)" :key="emp.id">
                            <div class="schedule-adjustment__employee bg-white dark:bg-slate-800 border"
                                 :class="ajustesBloqueados[emp.id] ? 'border-amber-200 dark:border-amber-500/40 bg-amber-50/10 dark:bg-amber-500/10' : 'border-slate-100 dark:border-slate-700 hover:border-red-200 dark:hover:border-red-400/50'">
                                
                                <div class="schedule-adjustment__person">
                                    <button type="button" @click="toggleBloqueo(emp.id)" title="Bloquear/Desbloquear horas" :aria-label="(ajustesBloqueados[emp.id] ? 'Desbloquear horas de ' : 'Bloquear horas de ') + emp.nombre + ' ' + (emp.apellidos || '')" :aria-pressed="!!ajustesBloqueados[emp.id]"
                                            class="schedule-adjustment__lock flex items-center justify-center schedule-adjustment__control shrink-0"
                                            :class="ajustesBloqueados[emp.id] ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-300 shadow-inner' : 'bg-slate-50 dark:bg-slate-900 text-slate-400 hover:bg-amber-50 dark:hover:bg-amber-500/20 hover:text-amber-500'">
                                        <!-- Icono Candado Cerrado -->
                                        <svg x-show="ajustesBloqueados[emp.id]" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                        <!-- Icono Candado Abierto -->
                                        <svg x-show="!ajustesBloqueados[emp.id]" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                    </button>
                                    
                                    <div class="flex-1 min-w-0 text-left">
                                        <h5 class="font-semibold text-slate-800 dark:text-slate-100 text-sm capitalize truncate" :title="emp.nombre + ' ' + (emp.apellidos || '')" x-text="emp.nombre + ' ' + (emp.apellidos || '')"></h5>
                                        <div class="schedule-adjustment__contract text-slate-500 dark:text-slate-400">
                                            <span>Contrato:</span>
                                            <span class="whitespace-nowrap" x-text="emp.horas_mensuales ? (emp.horas_mensuales / 4) + 'h sem' : 'Infinito'"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="schedule-adjustment__stepper bg-slate-50 dark:bg-slate-900" :class="{'opacity-50 pointer-events-none': ajustesBloqueados[emp.id]}">
                                    <button type="button" @click="restarHorasSemanales(emp)" :aria-label="'Restar media hora a ' + emp.nombre" :disabled="!!ajustesBloqueados[emp.id]" class="schedule-adjustment__step schedule-adjustment__control hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"></path></svg>
                                    </button>
                                    
                                    <div class="schedule-adjustment__hours">
                                        <input type="number" step="0.5" min="0" max="168" placeholder="—" :aria-label="'Horas semanales de ' + emp.nombre + ' ' + (emp.apellidos || '')" :disabled="!!ajustesBloqueados[emp.id]" class="schedule-adjustment__input text-red-600 dark:text-red-300"
                                               :value="ajustesManuales[emp.id] ?? ''"
                                               @change="setHorasSemanales(emp, $event.target.value)" />
                                        <span class="schedule-adjustment__hours-label text-slate-500 dark:text-slate-400">horas</span>
                                    </div>

                                    <button type="button" @click="sumarHorasSemanales(emp)" :aria-label="'Sumar media hora a ' + emp.nombre" :disabled="!!ajustesBloqueados[emp.id]" class="schedule-adjustment__step schedule-adjustment__control hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="schedule-adjustment__footer border-t border-slate-100 dark:border-slate-700">
                <div class="schedule-adjustment__hint text-xs text-slate-500 dark:text-slate-400">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Completa cada trabajador. Usa 0 si no debe recibir horas esta semana.
                </div>
                <div class="schedule-adjustment__actions">
                    <button type="button" @click="isAjusteManualOpen = false" class="schedule-adjustment__action schedule-adjustment__control bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                        Cancelar
                    </button>
                    <button type="button" @click="isAjusteManualOpen = false; generarHorario(false)" class="schedule-adjustment__action schedule-adjustment__control bg-gradient-to-r from-red-600 to-blue-600 border-0 bg-origin-border bg-no-repeat text-white hover:from-red-500 hover:to-blue-500">
                        <svg class="w-5 h-5 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        <span>Guardar y Generar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
