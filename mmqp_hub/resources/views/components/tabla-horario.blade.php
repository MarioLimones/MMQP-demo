<style>
    .bg-stripes {
        background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0, 0, 0, 0.05) 10px, rgba(0, 0, 0, 0.05) 20px);
    }
    .dark .bg-stripes {
        background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255, 255, 255, 0.05) 10px, rgba(255, 255, 255, 0.05) 20px);
    }
</style>
<div class="schedule-table-scroll overflow-x-auto w-full" tabindex="0" role="region" :aria-label="'Horario de ' + getRolPlural(rol) + '. Desplaza para ver todos los días.'">
    <table class="schedule-table w-full text-left border-collapse" :aria-label="'Horario semanal de ' + getRolPlural(rol)" x-bind:class="modoImpresion ? 'min-w-0' : 'min-w-[1000px]'">
        <thead>
            <tr>
                <th class="bg-gradient-to-b from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900 p-3 border-b-2 border-r border-slate-200 dark:border-slate-700 font-black text-slate-800 dark:text-slate-100 text-center uppercase text-xs tracking-widest sticky left-0 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]" x-bind:class="modoImpresion ? 'w-[12%]' : 'w-48 lg:w-56'">Empleado</th>
                <template x-for="dia in dias" :key="dia.nombre">
                    <th scope="col" :aria-label="dia.nombre + ' ' + (dia.fecha || '')" class="bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-800 dark:to-slate-800/80 p-3 border-b-2 border-r border-slate-200 dark:border-slate-700 font-black text-slate-700 dark:text-slate-200 text-center uppercase tracking-wider" x-bind:class="modoImpresion ? 'min-w-0' : 'min-w-[130px]'">
                        <span class="calendar-day-full text-sm" x-text="dia.nombre"></span>
                        <span class="calendar-day-short" :title="dia.nombre" x-text="dia.nombre.slice(0, 3)"></span>
                        <span class="calendar-day-date block text-[11px] text-slate-600 dark:text-slate-400 font-medium tracking-normal mt-0.5" :title="dia.fecha">
                            <span class="calendar-date-full" x-text="dia.fecha"></span>
                            <span class="calendar-date-short" x-text="dia.fecha ? dia.fecha.slice(8, 10) + '/' + dia.fecha.slice(5, 7) : ''"></span>
                        </span>
                    </th>
                </template>
                <th class="bg-gradient-to-b from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900 p-3 border-b-2 border-l border-slate-200 dark:border-slate-700 font-black text-slate-800 dark:text-slate-100 text-center uppercase text-xs tracking-widest" x-bind:class="modoImpresion ? (ocultarHoras ? 'hidden' : 'w-[10%]') : (ocultarHoras ? 'hidden' : 'w-32')">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="emp in getEmpleadosConTurno(rol)" :key="emp.id">
                <tr class="group hover:bg-red-50/20 dark:hover:bg-slate-800/30 transition-colors border-b border-slate-100 dark:border-slate-700/50 bg-white dark:bg-slate-900">
                    <td class="p-3 border-r border-slate-100 dark:border-slate-700/50 sticky left-0 bg-white dark:bg-slate-900 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.02)] align-top group-hover:bg-red-50/20 dark:group-hover:bg-slate-800/30 transition-colors">
                        <div class="flex flex-col gap-1 h-full justify-between">
                            <div class="flex items-start justify-between group/emp">
                                <div>
                                    <div class="calendar-employee-name font-black text-slate-800 dark:text-slate-100 text-sm md:text-[15px] uppercase tracking-wide leading-tight" x-text="emp.nombre"></div>
                                    <div class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mt-1" x-show="emp.horas_mensuales" x-text="emp.horas_mensuales + 'H/MES'"></div>
                                </div>
                                <!-- Los botones de reordenar se ocultan en impresión y si no es admin -->
                                <div class="flex flex-col opacity-10 group-hover:opacity-100 transition-opacity ml-2 print:hidden" x-show="!modoImpresion && esAdmin">
                                    <button type="button" :aria-label="'Subir a ' + emp.nombre" @click="moverArriba(emp.id, rol)" class="text-slate-600 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 dark:text-red-400 focus:outline-none"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg></button>
                                    <button type="button" :aria-label="'Bajar a ' + emp.nombre" @click="moverAbajo(emp.id, rol)" class="text-slate-600 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 dark:text-red-400 focus:outline-none"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></button>
                                </div>
                            </div>
                            <!-- Violaciones de restricciones -->
                            <div class="flex flex-wrap gap-1 mt-1 print:hidden" x-show="!modoImpresion && esAdmin">
                                <template x-if="getDiasTrabajados(emp.id, rol) > 5">
                                    <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-medium leading-snug text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" title="Trabaja más de 5 días">Trabaja más de cinco días esta semana</span>
                                </template>
                                <template x-if="!tieneDosDiasLibresConsecutivos(emp.id, rol) && getDiasTrabajados(emp.id, rol) <= 5 && getHorasSemanalesObjetivo(emp) > 0">
                                    <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-medium leading-snug text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" title="No tiene 2 días libres consecutivos">No tiene dos días libres seguidos</span>
                                </template>
                                <template x-for="infr in getInfraccionesDescanso(emp.id, rol)">
                                    <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-medium leading-snug text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" x-text="infr" title="No se cumple el descanso mínimo"></span>
                                </template>
                            </div>
                        </div>
                    </td>
                    
                    <template x-for="dia in dias" :key="dia.nombre">
                        <td class="p-2.5 border-r border-slate-100 dark:border-slate-700/50 align-top transition-colors bg-white/50 dark:bg-slate-900/50"
                            x-on:dragover.prevent="if(!modoImpresion && esAdmin) { dragOverDia = dia.nombre; dragOverEmp = emp.id; }"
                            x-on:dragleave.prevent="if(!modoImpresion && esAdmin && dragOverDia === dia.nombre && dragOverEmp === emp.id) { dragOverDia = null; dragOverEmp = null; }"
                            x-on:drop="if(!modoImpresion && esAdmin) soltarTurno(emp.id, dia.nombre, rol, emp.nombre)"
                            x-bind:class="[
                                (!modoImpresion && esAdmin) && dragOverDia === dia.nombre && dragOverEmp === emp.id ? 'bg-emerald-50 dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-800' : '',
                                getTurnosEmpleadoDia(emp.id, dia.nombre, rol).length === 0 && !emp.en_baja && !isVacaciones(emp, dia.fecha, dia.nombre) && !isFechaLibre(emp, dia.fecha, dia.nombre) ? 'bg-stripes opacity-75' : ''
                            ]">
                            <div class="flex flex-col gap-2 min-h-[40px] relative group/celda h-full">
                                <template x-if="emp.en_baja">
                                    <div class="h-full w-full flex items-center justify-center p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-lg pointer-events-none flex-1">
                                        <span class="text-red-500 font-bold text-[11px] uppercase text-center" x-text="emp.baja_motivo || 'Baja Médica'"></span>
                                    </div>
                                </template>

                                <template x-if="!emp.en_baja && isVacaciones(emp, dia.fecha, dia.nombre)">
                                    <div class="h-full w-full flex items-center justify-center p-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50 rounded-lg pointer-events-none flex-1">
                                        <span class="text-blue-500 font-bold text-[11px] uppercase text-center">Vacaciones</span>
                                    </div>
                                </template>

                                <template x-if="!emp.en_baja && !isVacaciones(emp, dia.fecha, dia.nombre) && isFechaLibre(emp, dia.fecha, dia.nombre)">
                                    <div class="h-full w-full flex items-center justify-center p-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-lg pointer-events-none flex-1">
                                        <span class="text-amber-500 font-bold text-[11px] uppercase text-center" x-text="isFestivoNoDisponible(emp, dia.fecha, dia.nombre) ? 'No disponible en festivo' : 'Libre Pedido'"></span>
                                    </div>
                                </template>

                                <template x-if="!emp.en_baja && !isVacaciones(emp, dia.fecha, dia.nombre) && !isFechaLibre(emp, dia.fecha, dia.nombre)">
                                    <div class="flex flex-col gap-2 min-h-[40px] w-full flex-1">
                                        <template x-for="turno in getTurnosEmpleadoDia(emp.id, dia.nombre, rol)" :key="turno.turno_id">
                                            <div class="calendar-shift rounded-lg p-2 text-center relative group/turno transition-all duration-200"
                                                 x-data="{ infr: getTurnoInfraccion(emp.id, rol, turno.turno_id) }"
                                                 x-bind:draggable="!modoImpresion && esAdmin"
                                                 x-on:dragstart="if(!modoImpresion && esAdmin) iniciarArrastre(turno.turno_id)"
                                                 @click="if(!modoImpresion && esAdmin) abrirModalEditarTurno(turno)"
                                                 x-bind:class="[
                                                    (!modoImpresion && esAdmin) ? 'cursor-pointer' : '',
                                                    (!modoImpresion && esAdmin && infr === 'same_day') ? 'bg-orange-50 border border-orange-400 shadow-sm' :
                                                    ((!modoImpresion && esAdmin && infr === 'next_day') ? 'bg-red-50 border border-red-400 shadow-sm' : 'bg-gradient-to-br from-white to-slate-50 dark:from-slate-800 dark:to-slate-800 border border-slate-200 dark:border-slate-700 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.08)] hover:border-red-300 dark:hover:border-red-500')
                                                 ]"
                                                 x-bind:style="draggedTurnoId === turno.turno_id ? 'opacity: 0.5;' : ''">
                                                <div class="calendar-shift-time font-black text-[13px] md:text-sm whitespace-nowrap pointer-events-none tracking-tight"
                                                     x-bind:class="(!modoImpresion && esAdmin && infr === 'same_day') ? 'text-orange-700' : ((!modoImpresion && esAdmin && infr === 'next_day') ? 'text-red-700' : 'text-slate-800 dark:text-slate-100')">
                                                    <span x-text="turno.hora_inicio"></span>&ndash;<span x-text="turno.hora_fin"></span>
                                                </div>
                                                <div class="text-xs font-bold pointer-events-none mt-0.5" x-show="turno.duracion_horas"
                                                     x-bind:class="(!modoImpresion && esAdmin && infr === 'same_day') ? 'text-orange-500' : ((!modoImpresion && esAdmin && infr === 'next_day') ? 'text-red-500' : 'text-slate-600 dark:text-slate-400')">
                                                    <span x-text="turno.duracion_horas + 'h'"></span>
                                                </div>
                                                <button type="button" x-show="!modoImpresion && esAdmin" @click.stop="eliminarTurno(turno.turno_id)" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-0 hidden group-hover/turno:flex items-center justify-center w-5 h-5 text-xs font-bold z-20 shadow-md hover:bg-red-600 focus:outline-none transition-transform hover:scale-110" title="Eliminar turno">&times;</button>
                                            </div>
                                        </template>
                                        
                                        <template x-if="getTotalHorasDia(emp.id, dia.nombre, rol) > 0">
                                            <div class="calendar-day-total text-center pt-1.5 mt-auto border-t border-slate-100 dark:border-slate-700/60 pointer-events-none"
                                                :class="{ 'calendar-day-total-redundant': getTurnosEmpleadoDia(emp.id, dia.nombre, rol).length === 1 }">
                                                <span class="text-xs font-extrabold tracking-wide"
                                                      x-bind:class="(!modoImpresion && esAdmin && getTotalHorasDia(emp.id, dia.nombre, rol) > 10) ? 'text-red-600 bg-red-50 px-1.5 py-0.5 border border-red-200 rounded shadow-sm' : 'text-slate-600 dark:text-slate-400'"
                                                      x-text="getTotalHorasDia(emp.id, dia.nombre, rol) + 'h'">
                                                </span>
                                            </div>
                                        </template>

                                        <template x-if="getTurnosEmpleadoDia(emp.id, dia.nombre, rol).length === 0">
                                            <div class="flex-1 w-full h-full min-h-[30px] flex items-center justify-center pointer-events-none rounded">
                                                <span class="text-slate-600 dark:text-slate-400 text-[10px] font-bold uppercase tracking-widest">Libre</span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                
                                <button type="button" x-show="!modoImpresion && esAdmin && !emp.en_baja && !isVacaciones(emp, dia.fecha, dia.nombre) && !isFechaLibre(emp, dia.fecha, dia.nombre)" @click.stop="abrirModalNuevoTurno(emp.id, dia.nombre, rol)" class="absolute bottom-0 right-0 m-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover/celda:opacity-100 transition-opacity shadow-sm z-20 print:hidden" title="Añadir turno manual">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                                </button>
                            </div>
                        </td>
                    </template>
                    
                    <td class="p-3 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800/80 dark:to-slate-900/80 border-l border-slate-200 dark:border-slate-700 text-center shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.05)] align-middle" x-bind:class="modoImpresion && ocultarHoras ? 'hidden' : (ocultarHoras ? 'hidden' : '')">
                        <div class="font-black text-sm md:text-base" 
                             x-bind:class="(!modoImpresion && esAdmin && getHorasSemanalesObjetivo(emp) > 0 && Math.abs(getTotalHorasSemana(emp.id, rol) - getHorasSemanalesObjetivo(emp)) > 0.01) ? 'text-red-600' : 'text-slate-800 dark:text-slate-100'"
                             x-text="getTotalHorasSemana(emp.id, rol) + ' h'"></div>
                        <div class="text-[9px] font-bold leading-tight mt-1 flex flex-col items-center gap-0.5 print:hidden" x-show="getHorasSemanalesObjetivo(emp) > 0 && !modoImpresion && esAdmin">
                            <template x-if="getHorasSemanalesObjetivo(emp) - getTotalHorasSemana(emp.id, rol) > 0.01">
                                <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-medium leading-snug text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200 text-center" x-text="'Faltan ' + (getHorasSemanalesObjetivo(emp) - getTotalHorasSemana(emp.id, rol)).toFixed(1) + ' h'"></span>
                            </template>
                            <template x-if="getTotalHorasSemana(emp.id, rol) - getHorasSemanalesObjetivo(emp) > 0.01">
                                <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-medium leading-snug text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200 text-center" x-text="'Sobran ' + (getTotalHorasSemana(emp.id, rol) - getHorasSemanalesObjetivo(emp)).toFixed(1) + ' h'"></span>
                            </template>

                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
