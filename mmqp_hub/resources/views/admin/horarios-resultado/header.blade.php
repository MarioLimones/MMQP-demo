<div class="calendar-toolbar flex flex-col items-center justify-center gap-4 mb-12 print:hidden w-full">
    <div class="calendar-week-heading flex w-full flex-col items-center gap-2">
    <div class="calendar-date-range text-sm font-bold text-slate-500 dark:text-slate-400 text-center">
        {{ $data['metadatos']['fecha_inicio'] ?? '' }} al {{ $data['metadatos']['fecha_fin'] ?? '' }}
    </div>
    <x-demo-schedule-label :results="$data['resultados'] ?? []" :description="true" />

    <!-- Selector de Semana (Arriba) -->
    <div class="calendar-week-selector flex items-center justify-center gap-4" x-data="{
        semana: '{{ $data['metadatos']['semana_id'] ?? '' }}',
        cambiarSemana(offset) {
            if(!this.semana) return;
            let [y, w] = this.semana.split('-W');
            y = parseInt(y); w = parseInt(w);
            w += offset;
            if(w < 1) { w = 52; y--; }
            if(w > 52) { w = 1; y++; }
            let nuevaSemana = y + '-W' + w.toString().padStart(2, '0');
            window.location.href = '?semana=' + nuevaSemana;
        }
    }">
        <button type="button" @click="cambiarSemana(-1)" aria-label="Semana anterior" class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all text-sm flex items-center" title="Semana Anterior">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <input type="week" x-model="semana" aria-label="Semana del horario" :disabled="isPublishing" @change="window.location.href = '?semana=' + $event.target.value" class="rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 shadow-sm focus:ring-red-500 font-bold text-center">
        <button type="button" @click="cambiarSemana(1)" aria-label="Semana siguiente" class="bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all text-sm flex items-center" title="Semana Siguiente">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
    </div>

    </div>

    <!-- Botones de Acción (Abajo) -->
    <div class="calendar-actions flex flex-wrap items-center justify-center gap-3 mt-2">
        @if(request()->routeIs('dashboard.calendario_global'))
            <div class="calendar-secondary-actions" x-data="{ optionsOpen: false, mobileTools: window.matchMedia('(max-width: 1023px)').matches }"
                @resize.window="mobileTools = window.matchMedia('(max-width: 1023px)').matches; if (!mobileTools) optionsOpen = false"
                @click.outside="optionsOpen = false"
                @keydown.escape.stop="optionsOpen = false; $refs.optionsToggle.focus()">
                <button type="button" x-ref="optionsToggle" class="calendar-options-toggle bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold"
                    @click="optionsOpen = !optionsOpen" :aria-expanded="optionsOpen" aria-controls="calendar-options-panel" aria-label="Opciones">
                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                    <span>Opciones</span>
                </button>
                <div id="calendar-options-panel" class="calendar-options-panel bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700"
                    :class="{ 'calendar-options-open': optionsOpen }" :inert="mobileTools && !optionsOpen"
                    :aria-hidden="mobileTools && !optionsOpen ? 'true' : null"
                    @click="if ($event.target.closest('.calendar-print')) optionsOpen = false">
        @endif
        <button type="button" @click="modoImpresion = true" class="calendar-print bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold py-2.5 px-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Imprimir
        </button>

        @if($es_borrador ?? false)
            <button type="button" @click="guardarResultado(true)" :disabled="isSaving || isPublishing || !!draftError"
                :aria-busy="isSaving ? 'true' : 'false'" aria-describedby="calendar-draft-status"
                :class="saveFeedback ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-500 hover:bg-amber-600'"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold text-white transition-colors disabled:cursor-wait disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500">
                <svg x-show="isSaving" style="display: none;" class="h-4 w-4 animate-spin motion-reduce:animate-none" aria-hidden="true" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <svg x-show="saveFeedback && !isSaving" style="display: none;" class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6"/></svg>
                <span x-text="isSaving ? 'Guardando…' : saveFeedback === 'saved' ? 'Borrador guardado' : saveFeedback === 'unchanged' ? 'Ya guardado' : 'Guardar borrador'">Guardar borrador</span>
            </button>
            <button type="button" @click="showPublicarModal = true" :disabled="isSaving || isPublishing || !!draftError" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white disabled:opacity-50">Publicar</button>
        @elseif(auth()->user()->es_admin && $id)
            <form class="calendar-edit" action="{{ route('admin.horarios.editar', $id) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white">Editar en un borrador independiente</button>
            </form>
        @endif
        @if(request()->routeIs('dashboard.calendario_global'))
                </div>
            </div>
        @endif
    </div>
    @if(request()->routeIs('dashboard.calendario_global'))
        <p class="calendar-orientation-hint text-center text-xs text-slate-600 dark:text-slate-300">Desliza para ver más días.</p>
    @endif
    <div x-show="saveState || draftError" class="calendar-save-status text-center text-sm">
        <p id="calendar-draft-status" role="status" aria-live="polite" aria-atomic="true"
            :class="{ 'font-semibold text-emerald-700 dark:text-emerald-400': !!saveFeedback }" x-text="saveState"></p>
        @if($es_borrador ?? false)
        @elseif(($data['estado'] ?? '') === 'empty')
            <p class="mt-1 text-slate-500 dark:text-slate-400">Selecciona otra semana para consultar sus turnos.</p>
        @elseif(($data['estado'] ?? '') === 'legacy')
            <p class="mt-1 text-amber-700">Registro antiguo: no se puede determinar si fue borrador o publicación. Se conserva sin cambios.</p>
        @elseif(($data['estado'] ?? '') === 'historico')
            @if($data['is_transfer'] ?? false)
                <p class="mt-1 text-slate-500 dark:text-slate-400">Horario importado de consulta. Se conserva la copia guardada y sus horas originales.</p>
            @else
                <p class="mt-1 text-slate-500 dark:text-slate-400">Registro documental de consulta. Las horas proceden de la columna Hora E/S del PDF.</p>
            @endif
        @else
            <p class="mt-1 text-slate-500 dark:text-slate-400">Publicación conservada. Para cambiar sus turnos, abre un borrador y publícalo explícitamente.</p>
        @endif
        <p x-show="draftError" style="display: none;" role="alert" class="mt-3 font-semibold text-red-700 dark:text-red-400" x-text="draftError"></p>
        <div x-show="draftError" style="display: none;" class="mt-3 flex flex-wrap justify-center gap-3">
            <button type="button" @click="guardarResultado(true)" :disabled="isSaving || isPublishing || draftSession?.blocked" class="rounded-lg border px-4 py-3 disabled:opacity-50" x-text="isSaving ? 'Guardando…' : 'Reintentar'">Reintentar</button>
            <button type="button" @click="descargarCambios()" class="rounded-lg border px-4 py-3">Descargar mis cambios</button>
            <button type="button" @click="window.location.reload()" class="rounded-lg border px-4 py-3">Recargar para revisar</button>
        </div>
    </div>
</div>
