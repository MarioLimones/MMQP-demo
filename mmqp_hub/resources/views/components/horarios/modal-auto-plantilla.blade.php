<dialog x-ref="autoTemplateDialog" aria-labelledby="auto-template-title" aria-describedby="auto-template-description auto-template-warning"
        @cancel.stop
        class="m-auto w-[calc(100%-2rem)] max-w-2xl max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
    <form @submit.prevent="generarPlantillaIA()" @invalid.capture="$event.target.closest('details')?.setAttribute('open', '')">
    <div class="p-4 sm:p-6">
        <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zm3 10h3m-3 4h7" />
            </svg>
        </div>
        <h2 id="auto-template-title" class="text-lg font-bold">Generar auto-plantilla</h2>
        </div>
        <div id="auto-template-description" class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200">Menores · hasta las 22:00</span>
            <span class="rounded-lg bg-slate-100 px-3 py-2 text-slate-600 dark:bg-slate-700 dark:text-slate-200">Resto · sin asignar</span>
        </div>
        <details class="mt-4 rounded-xl border border-slate-200 p-3 dark:border-slate-600">
            <summary class="cursor-pointer text-sm font-semibold">Franjas de trabajo</summary>
            <p id="auto-template-time-help" class="sr-only">Hora en formato HH:MM. Un fin anterior al inicio corresponde al día siguiente.</p>
            <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-600">
                <div aria-hidden="true" class="grid grid-cols-[4.5rem_repeat(2,minmax(0,1fr))] gap-2 bg-slate-50 px-2 py-2 text-center text-[11px] font-medium text-slate-500 sm:grid-cols-[7rem_repeat(2,minmax(0,1fr))] sm:px-3 dark:bg-slate-900/40 dark:text-slate-400">
                    <span class="text-left">Día</span>
                    <span>Mañana</span>
                    <span>Tarde</span>
                </div>
                <template x-for="dia in dias" :key="dia">
                    <div role="group" :aria-label="dia" class="grid grid-cols-[4.5rem_repeat(2,minmax(0,1fr))] items-center gap-2 border-t border-slate-200 px-2 py-1.5 sm:grid-cols-[7rem_repeat(2,minmax(0,1fr))] sm:px-3 dark:border-slate-700">
                        <label class="flex min-h-10 min-w-0 items-center gap-1.5 text-xs font-semibold capitalize sm:text-sm" :title="dia">
                            <input type="checkbox" x-model="autoTemplateHorario[dia].abierto" :aria-label="dia + ', día de trabajo'" class="h-3.5 w-3.5 shrink-0 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500">
                            <span class="truncate" x-text="dia"></span>
                        </label>
                        <template x-for="(franja, index) in autoTemplateHorario[dia].franjas" :key="index">
                            <div class="grid min-w-0 grid-cols-2 items-center gap-1">
                                <input type="text" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="HH:MM" pattern="(?:[01]?[0-9]|2[0-3])(?:[:.,]?[0-5][0-9])?" title="Hora entre 00:00 y 23:59" aria-describedby="auto-template-time-help" x-model="franja.inicio" @blur="franja.inicio = normalizarHoraPlantilla(franja.inicio)" :disabled="!autoTemplateHorario[dia].abierto || !franja.activa" :required="autoTemplateHorario[dia].abierto && franja.activa" :aria-label="dia + ', franja ' + (index + 1) + ', inicio'" class="h-9 w-full min-w-0 rounded-lg border-slate-300 bg-white px-1 text-center text-xs tabular-nums text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 disabled:opacity-40 sm:h-10 sm:px-2 sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                <input type="text" inputmode="numeric" autocomplete="off" spellcheck="false" placeholder="HH:MM" pattern="(?:[01]?[0-9]|2[0-3])(?:[:.,]?[0-5][0-9])?" title="Hora entre 00:00 y 23:59" aria-describedby="auto-template-time-help" x-model="franja.fin" @blur="franja.fin = normalizarHoraPlantilla(franja.fin)" :disabled="!autoTemplateHorario[dia].abierto || !franja.activa" :required="autoTemplateHorario[dia].abierto && franja.activa" :aria-label="dia + ', franja ' + (index + 1) + ', fin'" class="h-9 w-full min-w-0 rounded-lg border-slate-300 bg-white px-1 text-center text-xs tabular-nums text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 disabled:opacity-40 sm:h-10 sm:px-2 sm:text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                        </template>
                    </div>
                </template>
            </div>

        </details>
        <p x-show="autoTemplateError" x-cloak role="alert" x-text="autoTemplateError" class="mt-4 text-sm font-semibold text-red-700 dark:text-red-300"></p>
        <p id="auto-template-warning" class="mt-4 text-xs text-slate-500 dark:text-slate-400">Sustituirá los turnos de este borrador.</p>
    </div>
    <div class="sticky bottom-0 z-10 flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 dark:border-slate-700 dark:bg-slate-900">
        <button type="button" autofocus @click="$refs.autoTemplateDialog.close()" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Cancelar</button>
        <button type="submit" class="min-h-11 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Generar plantilla</button>
    </div>
    </form>
</dialog>

<dialog x-ref="autoTemplateLoadingDialog" aria-labelledby="auto-template-loading-title" aria-describedby="auto-template-loading-description"
        @cancel.prevent
        class="m-auto w-[calc(100%-2rem)] max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-900 shadow-2xl backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
    <div role="status" aria-live="polite" aria-atomic="true" class="flex flex-col items-center">
        <svg aria-hidden="true" class="mb-6 h-16 w-16 animate-spin text-emerald-600 motion-reduce:animate-none dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-90" d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
        <h2 id="auto-template-loading-title" class="text-lg font-bold">Generando plantilla…</h2>
        <p id="auto-template-loading-description" class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Estamos encajando las horas de cada trabajador según las franjas de trabajo, su disponibilidad y sus descansos.</p>
    </div>
</dialog>

<dialog x-ref="autoTemplateSuccessDialog" aria-labelledby="auto-template-success-title" aria-describedby="auto-template-success-description"
        @cancel.stop
        class="m-auto w-[calc(100%-2rem)] max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
    <div class="p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
            </div>
            <h2 id="auto-template-success-title" class="text-lg font-bold" x-text="autoTemplateApplied ? 'Plantilla lista' : 'No hay turnos disponibles'"></h2>
        </div>
        <p id="auto-template-success-description" class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300" x-text="autoTemplateApplied ? 'Revisa los turnos y pulsa Generar Horario.' : 'Revisa las horas y la disponibilidad. Tus turnos actuales se conservan.'"></p>
        <template x-if="autoTemplateResumen.length">
            <div class="mt-5 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-900/50">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Horas encajadas</p>
                <p class="mt-1 text-2xl font-bold tabular-nums tracking-tight" x-text="(autoTemplateResumen.reduce((s, e) => s + e.minutos_generados, 0) / 60).toLocaleString('es-ES', {maximumFractionDigits: 2}) + ' / ' + (autoTemplateResumen.reduce((s, e) => s + e.minutos_objetivo, 0) / 60).toLocaleString('es-ES', {maximumFractionDigits: 2}) + ' h'"></p>
                <p role="status" class="mt-2 text-sm font-medium" :class="autoTemplateResumen.some(e => e.minutos_generados < e.minutos_objetivo) ? 'text-amber-800 dark:text-amber-200' : 'text-emerald-700 dark:text-emerald-300'" x-text="autoTemplateResumen.some(e => e.minutos_generados < e.minutos_objetivo) ? (autoTemplateResumen.reduce((s, e) => s + Math.max(0, e.minutos_objetivo - e.minutos_generados), 0) / 60).toLocaleString('es-ES', {maximumFractionDigits: 2}) + ' h pendientes' : 'Todas las horas encajadas'"></p>
            </div>
        </template>

    </div>
    <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-4 dark:border-slate-700 dark:bg-slate-900/50">
        <button type="button" autofocus @click="$refs.autoTemplateSuccessDialog.close()" class="min-h-11 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Ver plantilla</button>
    </div>
</dialog>
