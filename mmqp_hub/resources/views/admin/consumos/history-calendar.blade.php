<section id="consumos-history" class="consumos-history print-hidden" aria-labelledby="consumos-history-title" data-history-url="{{ route('admin.consumos.historial') }}" data-history-month="{{ $history['month'] }}" data-history-day="{{ $history['selected_day'] }}">
    <div class="sr-only">
        <div>
            <h3 id="consumos-history-title">Registro de consumos</h3>
            <p>Consulta los consumos guardados, quién los registró y la versión de cada ficha.</p>
        </div>
    </div>

    <div class="consumos-history-toolbar">
        <nav class="consumos-history-navigation" aria-label="Mes del registro de consumos">
            <a class="arqueo-button consumos-history-month-arrow" href="{{ route('admin.consumos', ['month' => $history['previous_month']]) }}#consumos-history" aria-label="Ver mes anterior">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <a class="arqueo-button consumos-history-month-arrow" href="{{ route('admin.consumos', ['month' => $history['next_month']]) }}#consumos-history" aria-label="Ver mes siguiente">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </nav>
        <div class="consumos-history-month">
            <h4 id="consumos-history-month" aria-live="polite">{{ ucfirst($history['month_label']) }}</h4>
            <p class="sr-only">{{ $history['total_records'] }} {{ $history['total_records'] === 1 ? 'registro en este mes' : 'registros en este mes' }}</p>
        </div>
        <a class="arqueo-button consumos-history-go-today" href="{{ route('admin.consumos', ['month' => substr($history['today'], 0, 7), 'day' => $history['today']]) }}#consumos-history-day">Hoy</a>
    </div>

    <div class="consumos-history-calendar">
        <table aria-labelledby="consumos-history-month" aria-describedby="consumos-history-calendar-help">
            <caption class="sr-only">Calendario de consumos de {{ $history['month_label'] }}. Selecciona un día para consultar sus registros.</caption>
            <thead><tr>
                @foreach(['Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sábado' => 'Sáb', 'Domingo' => 'Dom'] as $weekday => $shortDay)
                    <th scope="col"><abbr title="{{ $weekday }}">{{ $shortDay }}</abbr></th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach(array_chunk($history['days'], 7) as $week)
                    <tr>
                        @foreach($week as $day)
                            @php
                                $count = count($day['records']);
                                $authors = collect($day['records'])->pluck('actor_name')->unique()->implode(', ');
                                $dayLabel = \Carbon\CarbonImmutable::parse($day['date'])->locale('es')->translatedFormat('j \d\e F \d\e Y');
                            @endphp
                            <td @class(['is-outside-month' => ! $day['in_month'], 'has-records' => $count > 0, 'is-today' => $day['is_today'], 'is-future' => $day['is_future'], 'is-selected' => $day['selected']])>
                                <a class="consumos-history-date" data-history-date="{{ $day['date'] }}" href="{{ route('admin.consumos', ['month' => substr($day['date'], 0, 7), 'day' => $day['date']]) }}#consumos-history-day" aria-label="{{ $day['day'] }}{{ $day['is_today'] ? ' Hoy' : '' }}. {{ $dayLabel }}: {{ $count }} {{ $count === 1 ? 'registro' : 'registros' }}{{ $authors ? '. '.$authors : '' }}{{ $day['is_future'] ? '. Día futuro' : '' }}{{ $day['selected'] ? '. Día seleccionado' : '' }}" @if($day['is_today']) aria-current="date" @endif>
                                    <span class="consumos-history-date-top"><time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time></span>
                                    @if($count > 0)
                                        <span class="consumos-history-count">{{ $count }}<span class="consumos-history-count-word"> {{ $count === 1 ? 'registro' : 'registros' }}</span></span>
                                        <span class="consumos-history-authors" title="{{ $authors }}">{{ $authors }}</span>
                                    @endif
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p id="consumos-history-calendar-help" class="sr-only">Los días con registros muestran su número y autor. Selecciona un día para revisar sus consumos debajo del calendario.</p>
    <p class="consumos-history-status" data-history-status role="status" aria-live="polite" aria-atomic="true"></p>
    @if($history['total_records'] === 0)
        <p class="consumos-history-month-empty">No hay consumos registrados en {{ $history['month_label'] }}.</p>
    @endif

    <section id="consumos-history-day" @class(['consumos-history-day', 'has-records' => count($history['selected_records']) > 0]) aria-labelledby="consumos-history-day-title" tabindex="-1">
        <div class="consumos-history-day-heading">
            <div>
                <p class="consumos-history-day-label">Consumos del día</p>
                <h4 id="consumos-history-day-title">{{ ucfirst($history['selected_label']) }}</h4>
            </div>
            <span class="consumos-history-day-count">{{ count($history['selected_records']) }} {{ count($history['selected_records']) === 1 ? 'registro guardado' : 'registros guardados' }}</span>
        </div>
        @if(count($history['selected_records']) > 0)
            <ol class="consumos-history-records">
                @foreach($history['selected_records'] as $entry)
                    <li class="consumos-history-record">
                        <span class="consumos-history-record-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg></span>
                        <div class="consumos-history-record-content">
                            <div class="consumos-history-record-title"><h5>{{ $entry['title'] }}</h5><span class="consumos-history-version">Versión {{ $entry['sheet_version'] }}</span></div>
                            <p class="consumos-history-record-author"><span class="sr-only">Registrado por </span><strong>{{ $entry['actor_name'] }}</strong><span class="consumos-history-record-separator" aria-hidden="true">·</span><span class="sr-only"> a las </span><time datetime="{{ $entry['registered_at'] }}">{{ $entry['registered_time'] }}</time></p>
                        </div>
                        @if($entry['import_file_name'] || $entry['period_start'] || $entry['period_end'])
                        <div class="consumos-history-record-meta">
                            @if($entry['import_file_name'])<p class="consumos-history-record-source consumos-history-record-file" title="{{ $entry['import_file_name'] }}"><span>PDF</span> {{ $entry['import_file_name'] }}</p>@endif
                            @if($entry['period_start'] || $entry['period_end'])
                                <p class="consumos-history-record-source consumos-history-record-period"><span>Periodo</span> {{ $entry['period_start'] ? \Carbon\CarbonImmutable::parse($entry['period_start'])->format('d/m/Y') : 'Sin inicio' }}@if($entry['period_end']) — {{ \Carbon\CarbonImmutable::parse($entry['period_end'])->format('d/m/Y') }}@endif</p>
                            @endif
                        </div>
                        @endif
                        <a class="arqueo-button consumos-history-record-open" href="{{ route('admin.consumos.historial.show', ['record' => $entry['id']]) }}" aria-label="Ver {{ $entry['title'] }}, versión {{ $entry['sheet_version'] }}, registrado por {{ $entry['actor_name'] }} a las {{ $entry['registered_time'] }}">Ver consumo <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></a>
                    </li>
                @endforeach
            </ol>
        @else
            <div class="consumos-history-day-empty"><p>No hay consumos registrados este día.</p><span>Los consumos aparecerán aquí al guardar una ficha.</span></div>
        @endif
    </section>
</section>
