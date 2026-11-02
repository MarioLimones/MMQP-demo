@php
    $metadata = $record->import_metadata ?? [];
    $pages = collect($snapshot['pages'] ?? [])->filter(fn ($page) => ! empty($page['grid_html']))->values();
    $registeredAt = \Carbon\CarbonImmutable::parse($record->registered_at)->timezone(\App\Services\ConsumoHistory::TIMEZONE);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="arqueo-heading"><div><h2 class="font-bold text-2xl text-slate-800 dark:text-white">Consumo registrado</h2><p class="text-sm text-slate-600 dark:text-slate-300 mt-1">Consulta la ficha tal como se guardó.</p></div></div>
    </x-slot>
    <div class="arqueo-workspace consumos-history-viewer" data-consumos-history-viewer>
        <section class="consumos-history-detail print-hidden" aria-labelledby="consumos-history-detail-title">
            <div class="consumos-history-detail-heading">
                <div><p class="consumos-history-detail-eyebrow">Registro guardado · Versión {{ $record->sheet_version }}</p><h3 id="consumos-history-detail-title">{{ $record->title }}</h3></div>
                <div class="consumos-history-detail-actions"><a class="arqueo-button arqueo-button-back" href="{{ $backUrl }}"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m6-6-6 6 6 6"/></svg>Volver al calendario</a><button type="button" class="arqueo-button arqueo-button-primary" data-consumos-history-print>Imprimir A4</button></div>
            </div>
            <dl class="consumos-history-metadata">
                <div><dt>Registrado por</dt><dd>{{ $record->actor_name }}</dd></div>
                <div><dt>Fecha y hora</dt><dd><time datetime="{{ $registeredAt->toIso8601String() }}">{{ $registeredAt->format('d/m/Y') }} a las {{ $registeredAt->format('H:i') }}</time></dd></div>
                <div><dt>PDF de origen</dt><dd>{{ $metadata['file_name'] ?? 'Sin PDF de origen' }}</dd></div>
                @if(! empty($metadata['period_start']) || ! empty($metadata['period_end']))
                    <div><dt>Periodo del consumo</dt><dd>{{ ! empty($metadata['period_start']) ? \Carbon\CarbonImmutable::parse($metadata['period_start'])->format('d/m/Y') : 'Sin inicio' }}@if(! empty($metadata['period_end'])) — {{ \Carbon\CarbonImmutable::parse($metadata['period_end'])->format('d/m/Y') }}@endif</dd></div>
                @endif
            </dl>
            <p class="consumos-history-readonly">Esta copia conserva los datos y el diseño de esta versión. Su contenido es de solo lectura.</p>
            <noscript><p class="consumos-history-help">Para imprimir la ficha, utiliza la opción Imprimir de tu navegador.</p></noscript>
        </section>

        <div class="consumos-history-pages">
            @forelse($pages as $page)
                <section class="consumos-history-page" aria-labelledby="consumos-history-page-title-{{ $loop->index }}">
                    <h3 id="consumos-history-page-title-{{ $loop->index }}" class="consumos-history-page-heading print-hidden"><span>{{ $page['title'] }}</span><span>Hoja {{ $loop->iteration }} de {{ $pages->count() }}</span></h3>
                    <div class="consumos-history-page-scroll" tabindex="0" role="region" aria-label="{{ $page['title'] }}. Desplázate horizontalmente para ver la hoja A4 completa en pantallas pequeñas.">
                        <div class="consumos-history-paper"><div class="consumos-history-paper-content">
                            <table class="consumos-history-grid" aria-label="{{ $page['title'] }}, copia guardada de la ficha de consumos"><colgroup>@for($column = 0; $column < 24; $column++)<col style="width: {{ 100 / 24 }}%;">@endfor</colgroup><tbody>{!! $page['grid_html'] !!}</tbody></table>
                        </div></div>
                    </div>
                </section>
            @empty
                <section class="consumos-history-legacy" aria-label="Datos del consumo guardado">
                    @forelse($snapshot['suppliers'] ?? [] as $supplier)
                        <div class="consumos-history-legacy-supplier"><h3>{{ $supplier['name'] }}</h3><div class="consumos-history-legacy-scroll" tabindex="0" role="region" aria-label="Productos de {{ $supplier['name'] }}"><table><thead><tr><th scope="col">Producto</th><th scope="col">Consumo</th><th scope="col">Stock</th><th scope="col">Pedido</th></tr></thead><tbody>
                            @foreach($supplier['products'] ?? [] as $product)
                                @php($amount = $product['consumption']['amount'] ?? null)
                                <tr><th scope="row">{{ $product['name'] }}</th><td>{{ $amount !== null ? (str_contains((string) $amount, '.') ? rtrim(rtrim((string) $amount, '0'), '.') : $amount).' '.($product['consumption']['unit'] ?? '') : '' }}</td><td>{{ $product['stock'] ?? '' }}</td><td>{{ $product['pedido'] ?? '' }}</td></tr>
                            @endforeach
                        </tbody></table></div></div>
                    @empty
                        <p class="consumos-history-day-empty">Esta versión no contiene hojas ni productos.</p>
                    @endforelse
                </section>
            @endforelse
        </div>
    </div>
</x-app-layout>
