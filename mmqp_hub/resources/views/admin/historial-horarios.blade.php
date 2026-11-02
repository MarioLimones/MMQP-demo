<x-app-layout>
    @php
        $transferFileError = $errors->first('archivo') ?: $errors->first('archivo.*');
    @endphp
    <x-slot name="header">
        <div class="history-toolbar">
            <h2 class="font-bold text-2xl text-slate-800 dark:text-white leading-tight flex items-center gap-2">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ __('Historial de Horarios') }}
            </h2>
            <div class="history-toolbar-actions">
                <details class="history-transfer-menu" x-data
                    @click.outside="$el.open = false"
                    @keydown.escape.stop.prevent="if ($el.open) { $el.open = false; $refs.transferToggle.focus() }"
                    @if($transferFileError || session('error')) open @endif>
                    <summary class="history-transfer-toggle" x-ref="transferToggle" aria-controls="history-transfer-panel">
                        <svg aria-hidden="true" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 3v12m0 0-4-4m4 4 4-4m4 10V9m0 0-4 4m4-4 4 4"/></svg>
                        Trasladar horarios
                        <svg class="history-transfer-chevron" aria-hidden="true" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <section id="history-transfer-panel" class="history-transfer" aria-labelledby="history-transfer-title">
                        <div class="history-transfer-heading">
                            <h3 id="history-transfer-title">Trasladar horarios</h3>
                            <a href="{{ route('admin.horarios.exportar') }}" class="history-transfer-button history-transfer-secondary">
                                <svg aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 15v4a2 2 0 002 2h10a2 2 0 002-2v-4"/></svg>
                                Exportar todos
                            </a>
                        </div>
                        <p id="history-transfer-description" class="history-transfer-description">Descarga tus horarios guardados en este ordenador y sube el JSON en la página desplegada.</p>
                        <form method="POST" action="{{ route('admin.horarios.importar') }}" enctype="multipart/form-data" class="history-transfer-form" aria-describedby="history-transfer-description history-transfer-note" x-data="{ submitting: false }" @submit="submitting = true" :aria-busy="submitting.toString()">
                            @csrf
                            <div class="history-transfer-field">
                                <label for="history-transfer-file">Archivo JSON de horarios</label>
                                <input id="history-transfer-file" type="file" name="archivo" accept=".json,application/json" required aria-describedby="history-transfer-file-help{{ $transferFileError ? ' history-transfer-file-error' : '' }}" @if($transferFileError) aria-invalid="true" @endif>
                                <p id="history-transfer-file-help" class="history-transfer-help">Formato JSON. Máximo {{ number_format($transferMaxKb / 1024, 2, ',', '.') }} MB.</p>
                                @if($transferFileError)
                                    <p id="history-transfer-file-error" class="history-transfer-error" role="alert">{{ $transferFileError }}</p>
                                @endif
                            </div>
                            <button type="submit" class="history-transfer-button history-transfer-primary" :disabled="submitting" x-text="submitting ? 'Importando…' : 'Importar JSON'">Importar JSON</button>
                        </form>
                        <p id="history-transfer-note" class="history-transfer-help">Se guardan como copias históricas, sin sustituir ni publicar los horarios actuales. Los horarios repetidos se omiten.</p>
                    </section>
                </details>
                <a href="{{ route('admin.horarios') }}" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-xl shadow-md transition-all text-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nuevo Horario
                </a>
            </div>
        </div>
    </x-slot>

    @include('admin.partials.horarios-historial-styles')

    <div class="schedule-history py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="history-notice history-notice-success" role="status">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="history-notice history-notice-error" role="alert">{{ session('error') }}</div>
            @endif

            <div class="history-panel bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
                @if($horarios->isEmpty())
                    <div class="text-center py-16 px-6">
                        <svg class="w-16 h-16 text-slate-300 dark:text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">No hay horarios guardados</h3>
                        <p class="text-slate-500 dark:text-slate-400 mb-6">Genera tu primer horario en el Diseñador para que aparezca aquí.</p>
                        <a href="{{ route('admin.horarios') }}" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl shadow-md transition-all text-sm inline-flex items-center gap-2">
                            Ir al Diseñador
                        </a>
                    </div>
                @else
                    <div class="history-years p-6">
                        @foreach($horariosAgrupados as $year => $months)
                            <div x-data="{ openYear: false }" :class="{ 'is-open': openYear }" class="history-year mb-4">
                                <h3 class="history-year-heading">
                                <button type="button" @click="openYear = !openYear" aria-expanded="false" :aria-expanded="openYear.toString()" aria-controls="history-year-{{ $year }}" class="history-year-toggle w-full flex items-center justify-between p-4 bg-slate-100 dark:bg-slate-700/50 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                    <span class="history-year-title text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2 capitalize">
                                        <svg class="w-5 h-5 text-slate-500 transition-transform" :class="openYear ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        Año {{ $year }}
                                    </span>
                                    <span class="history-disclosure-state" aria-hidden="true"><span x-show="!openYear">Ver meses</span><span x-show="openYear" x-cloak>Abierto</span></span>
                                    <span class="history-count text-sm font-medium text-slate-500 bg-white dark:bg-slate-800 px-3 py-1 rounded-full shadow-sm">{{ $months->flatten()->count() }} horarios</span>
                                </button>
                                </h3>
                                
                                <div id="history-year-{{ $year }}" x-show="openYear" x-cloak class="history-months mt-2 pl-4 space-y-4 ml-6">
                                    @foreach($months as $month => $items)
                                        <div x-data="{ openMonth: false }" :class="{ 'is-open': openMonth }" class="history-month">
                                            <h4 class="history-month-heading">
                                            <button type="button" @click="openMonth = !openMonth" aria-expanded="false" :aria-expanded="openMonth.toString()" aria-controls="history-month-{{ $year }}-{{ $month }}" class="history-month-toggle w-full flex items-center justify-between p-3 bg-white dark:bg-slate-800/80 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-red-300 dark:hover:border-red-900 transition-colors">
                                                <span class="history-month-title text-lg font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2 capitalize">
                                                    <svg class="w-4 h-4 text-slate-400 transition-transform" :class="openMonth ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                    {{ \Carbon\Carbon::createFromFormat('m', $month)->translatedFormat('F') }}
                                                </span>
                                                <span class="history-disclosure-state" aria-hidden="true"><span x-show="!openMonth">Ver horarios</span><span x-show="openMonth" x-cloak>Abierto</span></span>
                                                <span class="history-count text-xs font-medium text-slate-400">{{ $items->count() }} horarios</span>
                                            </button>
                                            </h4>
                                            
                                            <div id="history-month-{{ $year }}-{{ $month }}" x-show="openMonth" x-cloak tabindex="0" role="region" aria-label="Horarios de {{ \Carbon\Carbon::createFromFormat('m', $month)->translatedFormat('F') }} de {{ $year }}" class="history-table-scroll mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                                <table class="history-table w-full text-left border-collapse bg-white dark:bg-slate-800">
                                                    <caption class="sr-only">Horarios de {{ \Carbon\Carbon::createFromFormat('m', $month)->translatedFormat('F') }} de {{ $year }}</caption>
                                                    <thead>
                                                        <tr class="bg-slate-50 dark:bg-slate-900/50">
                                                            <th scope="col" class="p-4 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Semana y estado</th>
                                                            <th scope="col" class="p-4 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fechas</th>
                                                            <th scope="col" class="p-4 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Turnos cubiertos</th>
                                                            <th scope="col" class="p-4 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Generado por</th>
                                                            <th scope="col" class="p-4 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Guardado el</th>
                                                            <th scope="col" class="p-4 border-b border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                                        @foreach($items as $h)
                                                        @php
                                                            $historyState = $h->estado === 'historico' ? 'historico' : ($h->estado === 'borrador' ? 'borrador' : ($h->estado === 'legacy' ? 'legacy' : ($h->vigente ? 'publicado' : 'anterior')));
                                                            $historyStateLabel = match ($historyState) {
                                                                'historico' => 'Histórico importado',
                                                                'borrador' => 'Borrador pendiente de publicar',
                                                                'legacy' => 'Antiguo: estado ambiguo',
                                                                'publicado' => 'Publicado',
                                                                default => 'Publicación anterior',
                                                            };
                                                        @endphp
                                                        <tr class="history-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors" data-state="{{ $historyState }}">
                                                            <th scope="row" class="history-week-cell p-4 font-bold text-slate-800 dark:text-white">
                                                                {{ $h->semana_id }}
                                                                <x-demo-schedule-label :results="$h->resultados ?? []" />
                                                                <span class="history-state-line"><span class="history-state"><span class="history-state-dot" aria-hidden="true"></span>{{ $historyStateLabel }}</span><span class="history-version">v{{ $h->version }}</span></span>
                                                            </th>
                                                            <td class="p-4 text-slate-500 dark:text-slate-400 text-sm">
                                                                {{ $h->parsed_fecha_inicio->format('d/m/Y') }} &rarr; {{ $h->parsed_fecha_fin->format('d/m/Y') }}
                                                            </td>
                                                            <td class="p-4">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $h->turnos_cubiertos >= $h->turnos_totales ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' }}">
                                                                        {{ $h->turnos_cubiertos }} / {{ $h->turnos_totales }}
                                                                    </span>
                                                                    @if($h->turnos_totales > 0)
                                                                        <span class="text-xs text-slate-400 dark:text-slate-500 font-medium">
                                                                            ({{ round($h->turnos_cubiertos / $h->turnos_totales * 100) }}%)
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            <td class="p-4 text-slate-500 dark:text-slate-400 text-sm">
                                                                <div class="flex items-center gap-2">
                                                                    <span>{{ $h->generador?->nombre ?? '—' }}</span>
                                                                    @if($h->is_importado)
                                                                        <span class="history-origin inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 uppercase tracking-wide">
                                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                                            Importado
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            <td class="p-4 text-slate-500 dark:text-slate-400 text-sm">
                                                                {{ $h->created_at->format('d/m/Y H:i') }}
                                                            </td>
                                                            <td class="p-4 text-right">
                                                                <div class="flex justify-end gap-2">
                                                                    <a href="{{ route('admin.horarios.resultado', $h->id) }}" aria-label="Ver horario {{ $h->semana_id }}, {{ $historyStateLabel }}" class="history-view inline-flex items-center gap-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold py-1.5 px-3 rounded-lg text-sm transition-colors">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                                        Ver horario
                                                                    </a>
                                                                    <a href="{{ route('admin.horarios.exportar', ['id' => $h->id]) }}" aria-label="Exportar JSON del horario {{ $h->semana_id }}, versión {{ $h->version }}" class="history-export history-transfer-button history-transfer-secondary">Exportar JSON</a>
                                                                    @if($h->estado === 'borrador')
                                                                    <form method="POST" action="{{ route('admin.horarios.historial.eliminar', $h->id) }}" class="inline">
                                                                        @csrf @method('DELETE')
                                                                        <input type="hidden" name="semana_id" value="{{ $h->semana_id }}">
                                                                        <input type="hidden" name="version" value="{{ $h->version }}">
                                                                        <button type="submit" aria-label="Eliminar borrador {{ $h->semana_id }}" title="Eliminar borrador" class="history-delete inline-flex items-center gap-1 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 font-bold py-1.5 px-3 rounded-lg text-sm transition-colors" onclick="return confirm('¿Eliminar este borrador? La publicación se conservará.')">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                        </button>
                                                                    </form>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
