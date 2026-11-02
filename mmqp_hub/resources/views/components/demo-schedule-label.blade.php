@props(['results' => [], 'description' => false])
@if(config('demo.enabled') && count($results) > 0 && collect($results)->every(fn ($shift) => str_starts_with((string) ($shift['turno_id'] ?? ''), 'demo-')))
    <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">
        <strong class="font-semibold">DEMO · Horario de ejemplo</strong>
        @if($description)
            <p class="mt-1">Lo hemos creado para que puedas explorar la aplicación. Los nombres y los turnos son inventados.</p>
        @endif
    </div>
@endif
