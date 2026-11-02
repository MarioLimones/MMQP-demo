@if($snapshot)
    @if(isset($snapshot['original_attributes']))
        <p class="text-sm">Instantánea original importada. Diagnóstico: <strong>{{ $snapshot['assessment']['status'] }}</strong>.</p>
        <p class="text-sm mt-2">{{ $snapshot['limitations'] }}</p>
        @if(!empty($snapshot['assessment']['differences']))
            <ul class="list-disc ml-5 mt-2 text-sm">
                @foreach($snapshot['assessment']['differences'] as $field => $difference)
                    <li>{{ $field }}: original {{ $difference['original'] ?? 'No registrado' }}; fórmula actual {{ $difference['recalculated'] }}.</li>
                @endforeach
            </ul>
        @endif
        @if(isset($snapshot['assessment']['message']))<p>{{ $snapshot['assessment']['message'] }}</p>@endif
    @else
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            @foreach($snapshot['results'] as $field => $amount)<div><dt class="font-medium">{{ str_replace('_', ' ', $field) }}</dt><dd>{{ $amount }} €</dd></div>@endforeach
        </dl>
        <p class="text-sm mt-3">Fórmula {{ $snapshot['formula'] }}. Referencias: declaraciones del empleado, conservadas con esta revisión.</p>
        <p class="text-sm">Responsable declarado: {{ $snapshot['responsible']['nombre'] }} {{ $snapshot['responsible']['apellidos'] ?? '' }}.</p>
    @endif
    <details class="mt-3">
        <summary class="cursor-pointer underline">Consultar entradas y referencias completas</summary>
        <pre class="whitespace-pre-wrap break-all text-xs bg-slate-100 dark:bg-slate-900 p-3 rounded mt-2">{{ json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
@else
    <p>Sin versión anterior: creación del cuadre.</p>
@endif
