<x-app-layout>
    <style>
        .cash-day-panel { position: fixed; z-index: 60; width: min(360px, calc(100% - 24px)); max-height: min(560px, calc(100dvh - 24px)); background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 12px 40px rgb(15 23 42 / .25); display: flex; flex-direction: column; overflow: hidden; }
        .cash-day-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        .cash-day-close { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 8px; flex-shrink: 0; }
        .cash-day-close:hover { background: #f1f5f9; }
        .cash-day-close:focus-visible { outline: 3px solid #818cf8; outline-offset: 2px; }
        .cash-day-panel-content { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 16px; }
        .cash-day-record { border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
        .cash-day-record:last-child { border-bottom: 0; padding-bottom: 0; margin-bottom: 0; }
        .cash-day-values { display: grid; gap: 6px; font-size: 14px; }
        .cash-day-values > div { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; }
        .cash-day-values dt { color: #64748b; }
        .cash-day-values dd { text-align: right; font-weight: 700; overflow-wrap: anywhere; }
        .dark .cash-day-panel { background: #1e293b; color: #f8fafc; border-color: #334155; }
        .dark .cash-day-panel-heading, .dark .cash-day-record { border-color: #334155; }
        .dark .cash-day-values dt { color: #94a3b8; }
        .dark .cash-day-close:hover { background: #334155; }
        .cash-calendar-toolbar { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 8px; margin-bottom: 16px; }
        .cash-calendar-nav { display: flex; gap: 6px; }
        .cash-calendar-month-form { position: relative; justify-self: end; min-width: 0; max-width: 100%; color: #1e293b; }
        .cash-calendar-month { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: min(200px, 100%); min-height: 44px; border: 1px solid #94a3b8; border-radius: 8px; padding: 8px 12px; font-size: 14px; cursor: pointer; list-style: none; }
        .cash-calendar-month::-webkit-details-marker { display: none; }
        .cash-calendar-month:hover, .cash-calendar-month-form[open] > summary { background: #eef2ff; border-color: #818cf8; }
        .cash-calendar-month-popup { position: absolute; z-index: 65; right: 0; top: calc(100% + 8px); width: 280px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 12px 32px rgb(15 23 42 / .22); }
        .cash-calendar-year { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; }
        .cash-calendar-year button { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 8px; }
        .cash-calendar-months { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
        .cash-calendar-months a { display: grid; place-items: center; min-height: 40px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .cash-calendar-year button:hover, .cash-calendar-months a:hover { background: #eef2ff; }
        .cash-calendar-months a[aria-current=date] { background: #e2e8f0; color: #1e293b; }
        .cash-calendar-month-form :focus-visible { outline: 3px solid #818cf8; outline-offset: 2px; }
        .dark .cash-calendar-month-form { color: #f8fafc; }
        .dark .cash-calendar-month { border-color: #64748b; }
        .dark .cash-calendar-month-popup { background: #1e293b; border-color: #475569; }
        .dark .cash-calendar-year { border-color: #475569; }
        .dark .cash-calendar-month:hover, .dark .cash-calendar-month-form[open] > summary, .dark .cash-calendar-year button:hover, .dark .cash-calendar-months a:hover { background: #334155; }
        .dark .cash-calendar-months a[aria-current=date] { background: #475569; color: #f8fafc; }
        .cash-calendar-button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 6px 13px; border: 1px solid #4f46e5; border-radius: 8px; background: #4f46e5; color: #fff; font-weight: 600; transition: background-color .2s; }
        .cash-calendar-arrow { width: 40px; padding: 6px; }
        .cash-calendar-button:hover { background: #4338ca; border-color: #4338ca; }
        .cash-calendar-button:focus-visible { outline: 3px solid #818cf8; outline-offset: 3px; }
        .cash-calendar-title { font-size: 20px; font-weight: 800; line-height: 1.3; text-align: center; text-transform: capitalize; color: #1e293b; }
        .cash-calendar-weekday { padding: 4px 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #475569; }
        .cash-calendar-day { box-sizing: border-box; width: 100%; min-width: 0; min-height: 100px; padding: 6px; }
        .cash-shift-short { display: none; }
        .cash-calendar-date { display: block; flex-shrink: 0; text-align: right; font-size: .9rem; font-weight: 600; color: #334155; }
        .cash-calendar-outside .cash-calendar-date { opacity: .5; }
        .dark .cash-calendar-title, .dark .cash-calendar-date { color: #f8fafc; }
        .dark .cash-calendar-weekday { color: #cbd5e1; }
        .dark .cash-calendar-button { background: #6366f1; border-color: #6366f1; }
        .dark .cash-calendar-button:hover { background: #4f46e5; border-color: #4f46e5; }
        @media (max-width: 639px) {
            .cash-calendar-toolbar { grid-template-columns: auto minmax(0, 1fr); gap: 12px 6px; }
            .cash-calendar-title { grid-column: 1 / -1; grid-row: 1; }
            .cash-calendar-nav, .cash-calendar-month-form { grid-row: 2; }
        }
        @media (max-width: 479px) {
            .cash-calendar-title { font-size: 16px; }
            .cash-calendar-day { padding-inline: 3px; }
            .cash-calendar-badge { padding-inline: 2px; font-size: 9px; letter-spacing: -.02em; }
        }
        @media (max-width: 360px) {
            .cash-shift-full { display: none; }
            .cash-shift-short { display: inline; text-decoration: none; }
        }
    </style>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-800 dark:text-white leading-tight flex items-center gap-2">
            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ __('Historial de Cuadre de Caja') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section aria-label="Resumen del mes" class="bg-white dark:bg-slate-800 p-4 sm:p-6 rounded-2xl border border-slate-200 dark:border-slate-700">
                <dl class="flex flex-wrap items-center gap-x-12 gap-y-4">
                    <div>
                        <dt class="text-sm text-slate-500 dark:text-slate-400">Ingreso total registrado</dt>
                        <dd class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $summary['ingreso_total'] }} €</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500 dark:text-slate-400">Cierres registrados</dt>
                        <dd class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $summary['cierres'] }}</dd>
                    </div>
                </dl>
                @if($summary['cierres_legacy'])<p class="mt-4 text-sm text-amber-700 dark:text-amber-400">{{ $summary['cierres_legacy'] }} cierres antiguos conservan sus importes sin verificar. Cada cierre contribuye una vez al total. Consulta sus diagnósticos.</p>@endif
                @if($summary['legacy_sin_importe'])<p class="mt-4 text-sm text-amber-700 dark:text-amber-400">{{ $summary['legacy_sin_importe'] }} cierres antiguos carecen de un ingreso válido; el total está incompleto.</p>@endif

            </section>
            
            @php
                $daysInMonth = $startDate->daysInMonth;
                $firstDayOfWeek = $startDate->copy()->startOfMonth()->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
                
                $recordsByDate = $historial->groupBy(function($item) {
                    return \Carbon\Carbon::parse($item->fecha)->format('Y-m-d');
                });

                $prevMonth = $startDate->copy()->subMonth()->format('Y-m');
                $nextMonth = $startDate->copy()->addMonth()->format('Y-m');
                
                // Helper for month name
                $monthName = ucfirst($startDate->translatedFormat('F \d\e Y'));
            @endphp

            <div class="cash-calendar bg-white dark:bg-slate-800 shadow-sm rounded-2xl p-4 sm:p-6" x-data="{
                selectedDate: null, panelLeft: 12, panelTop: 12, panelBottomInset: 12,
                openDay(day, cell) {
                    if (this.selectedDate === day) { this.closeDay(); return; }
                    this.selectedDate = day;
                    this.$nextTick(() => {
                        this.positionDay(cell);
                        this.$refs.dayHeading.focus({ preventScroll: true });
                        this.$refs.dayContent.scrollTop = 0;
                    });
                },
                positionDay(cell) {
                        if (!cell) return;
                        const anchor = cell.getBoundingClientRect();
                        const calendar = this.$root.getBoundingClientRect();
                        const panel = this.$refs.dayPanel.getBoundingClientRect();
                        const navigation = this.$root.ownerDocument.querySelector('.app-bottom-navigation')?.getBoundingClientRect();
                        this.panelBottomInset = navigation?.height ? Math.max(12, window.innerHeight - navigation.top + 12) : 12;
                        const panelHeight = Math.min(panel.height, Math.max(120, window.innerHeight - this.panelBottomInset - 12));
                        const leftEdge = Math.max(12, calendar.left);
                        const rightEdge = Math.min(window.innerWidth - 12, calendar.right);
                        const preferredLeft = anchor.right + 12 + panel.width <= rightEdge ? anchor.right + 12 : anchor.left - panel.width - 12;
                        this.panelLeft = Math.max(leftEdge, Math.min(preferredLeft, rightEdge - panel.width));
                        this.panelTop = Math.max(12, Math.min(anchor.top, window.innerHeight - panelHeight - this.panelBottomInset));
                },
                resizeDay() {
                    if (this.selectedDate) this.$nextTick(() => this.positionDay(Array.from(this.$root.querySelectorAll('[data-cash-date]')).find(el => el.dataset.cashDate === this.selectedDate)));
                },
                closeDay() { const day = this.selectedDate; this.selectedDate = null; this.$nextTick(() => Array.from(this.$root.querySelectorAll('[data-cash-date]')).find(el => el.dataset.cashDate === day)?.focus({ preventScroll: true })); }
            }" @keydown.escape.window="if (selectedDate) closeDay()" @resize.window="resizeDay()">
                
                <!-- Calendar Header -->
                <div class="cash-calendar-toolbar">
                    <div class="cash-calendar-nav">
                        <a href="{{ route('admin.cuadre.historial', ['month' => $prevMonth]) }}" class="cash-calendar-button cash-calendar-arrow" aria-label="Mes anterior" title="Mes anterior">
                            <svg aria-hidden="true" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </a>
                        <a href="{{ route('admin.cuadre.historial', ['month' => $nextMonth]) }}" class="cash-calendar-button cash-calendar-arrow" aria-label="Mes siguiente" title="Mes siguiente">
                            <svg aria-hidden="true" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                    <h3 class="cash-calendar-title">{{ $monthName }}</h3>
                    <details class="cash-calendar-month-form" x-data="{ year: {{ $startDate->year }} }" @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false; $el.querySelector('summary').focus()">
                        <summary class="cash-calendar-month" aria-label="Elegir mes y año">
                            <span>{{ $startDate->translatedFormat('F \d\e Y') }}</span>
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>
                        </summary>
                        <div class="cash-calendar-month-popup">
                            <div class="cash-calendar-year">
                                <button type="button" aria-label="Año anterior" @click="year = Math.max(1, year - 1)"><svg aria-hidden="true" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="m14 6-6 6 6 6"/></svg></button>
                                <strong aria-live="polite" x-text="year">{{ $startDate->year }}</strong>
                                <button type="button" aria-label="Año siguiente" @click="year = Math.min(9999, year + 1)"><svg aria-hidden="true" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="m10 6 6 6-6 6"/></svg></button>
                            </div>
                            <nav class="cash-calendar-months" aria-label="Elegir mes">
                                @foreach(['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'] as $monthLabel)
                                    @php
                                        $monthNumber = str_pad($loop->iteration, 2, '0', STR_PAD_LEFT);
                                    @endphp
                                    <a href="{{ route('admin.cuadre.historial', ['month' => $startDate->format('Y').'-'.$monthNumber]) }}"
                                       data-month-url="{{ route('admin.cuadre.historial') }}"
                                       :href="$el.dataset.monthUrl + '?month=' + String(year).padStart(4, '0') + '-{{ $monthNumber }}'"
                                       :aria-label="'{{ \Carbon\Carbon::create(2000, $loop->iteration, 1)->locale('es')->monthName }} de ' + year"
                                       :aria-current="year === {{ $startDate->year }} &amp;&amp; {{ $loop->iteration }} === {{ $startDate->month }} ? 'date' : null"
                                       @if($loop->iteration === $startDate->month) aria-current="date" @endif>{{ $monthLabel }}</a>
                                @endforeach
                            </nav>
                        </div>
                    </details>
                </div>

                <!-- Calendar Grid -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden mb-8">
                    <div class="grid grid-cols-7 bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-center">
                        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dayName)
                            <div class="cash-calendar-weekday">{{ $dayName }}</div>
                        @endforeach
                    </div>
                    
                    <div class="grid grid-cols-7 bg-slate-200 dark:bg-slate-700 gap-[1px]">
                        <!-- Padding days -->
                        @for($i = 1; $i < $firstDayOfWeek; $i++)
                            <div class="cash-calendar-day cash-calendar-outside bg-white dark:bg-slate-800"><span class="cash-calendar-date">{{ $startDate->copy()->startOfMonth()->subDays($firstDayOfWeek - $i)->day }}</span></div>
                        @endfor

                        <!-- Month days -->
                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $currentDate = $startDate->copy()->addDays($day - 1)->format('Y-m-d');
                                $dayRecords = $recordsByDate->get($currentDate, collect());
                                $hasApertura = $dayRecords->where('turno', 'apertura')->isNotEmpty();
                                $hasIntermedio = $dayRecords->where('turno', 'intermedio')->isNotEmpty();
                                $hasCierre = $dayRecords->where('turno', 'cierre')->isNotEmpty();
                                $isToday = $currentDate === \Carbon\Carbon::today()->format('Y-m-d');
                            @endphp

                            <div @click="openDay('{{ $currentDate }}', $event.currentTarget)"
                                 data-cash-date="{{ $currentDate }}" aria-controls="cash-day-panel" :aria-expanded="selectedDate === '{{ $currentDate }}'" role="button" tabindex="0" aria-label="{{ \Carbon\Carbon::parse($currentDate)->translatedFormat('j \d\e F \d\e Y') }}"
                                 @keydown.enter.prevent="$el.click()" @keydown.space.prevent="$el.click()"
                                 :aria-pressed="selectedDate === '{{ $currentDate }}'"
                                 class="cash-calendar-day bg-white dark:bg-slate-800 flex flex-col transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 focus-visible:-outline-offset-2 {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-500/25' : '' }}"
                                 :class="{
                                     'ring-2 ring-inset ring-blue-500 bg-blue-50/50 dark:bg-slate-700': selectedDate === '{{ $currentDate }}',
                                     'hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer': {{ $dayRecords->count() }} > 0,
                                 }">
                                <div class="mb-1">
                                    <span class="cash-calendar-date" @if($isToday) aria-current="date" @endif>
                                        {{ $day }}
                                    </span>
                                </div>
                                
                                <div class="space-y-1 flex-1">
                                    @if($hasApertura) 
                                        <div class="cash-calendar-badge text-[10px] sm:text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 rounded px-1.5 py-0.5 truncate border border-amber-200 dark:border-amber-800/50"><span class="cash-shift-full">Apertura</span><abbr class="cash-shift-short" title="Apertura">Aper.</abbr></div> 
                                    @endif
                                    @if($hasIntermedio) 
                                        <div class="cash-calendar-badge text-[10px] sm:text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 rounded px-1.5 py-0.5 truncate border border-indigo-200 dark:border-indigo-800/50"><span class="cash-shift-full">Intermedio</span><abbr class="cash-shift-short" title="Intermedio">Inter.</abbr></div> 
                                    @endif
                                    @if($hasCierre) 
                                        <div class="cash-calendar-badge text-[10px] sm:text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 rounded px-1.5 py-0.5 truncate border border-emerald-200 dark:border-emerald-800/50">Cierre</div> 
                                    @endif
                                </div>
                            </div>
                        @endfor
                        
                        <!-- Padding end days -->
                        @php
                            $totalCells = $firstDayOfWeek - 1 + $daysInMonth;
                            $remainingCells = 42 - $totalCells; // Always 6 rows for safety
                        @endphp
                        @for($i = 0; $i < $remainingCells; $i++)
                            <div class="cash-calendar-day cash-calendar-outside bg-white dark:bg-slate-800"><span class="cash-calendar-date">{{ $i + 1 }}</span></div>
                        @endfor
                    </div>
                </div>

                <aside id="cash-day-panel" x-ref="dayPanel" class="cash-day-panel" :style="{ left: panelLeft + 'px', top: panelTop + 'px', maxHeight: Math.min(560, window.innerHeight - panelTop - panelBottomInset) + 'px' }" x-show="selectedDate" x-cloak aria-labelledby="cash-day-title" @keydown.tab="if (window.matchMedia('(max-width: 479px)').matches) { const controls = Array.from($el.querySelectorAll('button, a[href], summary')).filter(el => el.getClientRects().length); const first = controls[0]; const last = controls[controls.length - 1]; if ($event.shiftKey &amp;&amp; (document.activeElement === first || document.activeElement === $refs.dayHeading)) { $event.preventDefault(); last.focus(); } else if (!$event.shiftKey &amp;&amp; document.activeElement === last) { $event.preventDefault(); first.focus(); } }">
                    <div class="cash-day-panel-heading">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Resumen del día</p>
                            <h3 id="cash-day-title" class="mt-1 text-xl font-bold text-slate-900 dark:text-white"><button type="button" x-ref="dayHeading" @click="closeDay()" class="rounded underline decoration-dotted underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500" :aria-label="'Cerrar resumen del ' + (selectedDate ? selectedDate.split('-').reverse().join('/') : 'día')" x-text="selectedDate ? selectedDate.split('-').reverse().join('/') : ''"></button></h3>
                        </div>
                        <button type="button" @click="closeDay()" class="cash-day-close" aria-label="Cerrar resumen del día"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
                    </div>
                    <div class="cash-day-panel-content" x-ref="dayContent">
                        <p x-show="!{{ \Illuminate\Support\Js::from($recordsByDate->keys()->values()) }}.includes(selectedDate)" class="text-sm text-slate-500 dark:text-slate-400">{{ $historial->isEmpty() ? 'No hay cuadres de caja registrados en este mes.' : 'No hay cuadres registrados en este día.' }}</p>
                        @foreach($historial as $cuadre)
                            @php
                                $recordDate = \Carbon\Carbon::parse($cuadre->fecha)->format('Y-m-d');
                            @endphp
                            <section x-show="selectedDate === '{{ $recordDate }}'" class="cash-day-record" aria-label="{{ ucfirst($cuadre->turno) }}">
                                <h4 class="mb-3 text-sm font-bold uppercase {{ $cuadre->turno === 'apertura' ? 'text-amber-700 dark:text-amber-400' : ($cuadre->turno === 'cierre' ? 'text-emerald-700 dark:text-emerald-400' : 'text-indigo-700 dark:text-indigo-400') }}">{{ $cuadre->turno }}</h4>
                                <dl class="cash-day-values">
                                    <div><dt>Encargado</dt><dd>{{ $cuadre->calculation_snapshot['responsible']['nombre'] ?? $cuadre->responsible->nombre ?? $cuadre->user->nombre ?? 'Desconocido' }}</dd></div>
                                    <div><dt>Total en caja</dt><dd>{{ number_format($cuadre->total_calculado, 2) }} €</dd></div>
                                    @if($cuadre->turno === 'cierre')
                                        <div><dt>Ingresado</dt><dd>{{ $cuadre->ingreso_real === null ? 'Sin registrar' : number_format($cuadre->ingreso_real, 2).' €' }}</dd></div>
                                        <div><dt>Fondo restante</dt><dd>{{ $cuadre->ingreso_real === null ? 'Sin registrar' : number_format($cuadre->total_calculado - $cuadre->ingreso_real, 2).' €' }}</dd></div>
                                    @endif
                                    <div><dt>Descuadre</dt><dd class="{{ $cuadre->descuadre < 0 ? 'text-red-600 dark:text-red-400' : ($cuadre->descuadre > 0 ? 'text-amber-700 dark:text-amber-400' : '') }}">{{ $cuadre->descuadre > 0 ? '+' : '' }}{{ number_format($cuadre->descuadre, 2) }} €</dd></div>
                                </dl>

                            </section>
                        @endforeach
                    </div>
                </aside>

            </div>

        </div>
    </div>
</x-app-layout>
