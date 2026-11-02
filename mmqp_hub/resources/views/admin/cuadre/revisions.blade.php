<x-app-layout>
    <x-slot name="header"><h2 class="font-bold text-2xl">Modificaciones del cuadre · {{ $cuadre->fecha->format('d/m/Y') }} · {{ $cuadre->turno }}</h2></x-slot>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 space-y-6 text-slate-900 dark:text-slate-100">
        <div class="flex flex-wrap gap-4">
            <a class="underline" href="{{ route('admin.cuadre', ['fecha' => $cuadre->fecha->toDateString(), 'turno' => $cuadre->turno]) }}">Abrir versión vigente {{ $cuadre->version }}</a>
            <a class="underline" href="{{ route('admin.cuadre.historial', ['month' => $cuadre->fecha->format('Y-m')]) }}">Volver al historial mensual</a>
        </div>
        @foreach($revisions as $revision)
            <article class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 sm:p-6 rounded-xl space-y-3">
                <h3 class="text-lg font-bold">Versión {{ $revision->version }} {{ $revision->version === $cuadre->version ? '· vigente' : '' }} · {{ ['legacy_import' => 'Importación de registro antiguo', 'creation' => 'Creación', 'correction' => 'Rectificación'][$revision->origin] }}</h3>
                <p>Registrada por el servidor: {{ $revision->recorded_at->format('d/m/Y H:i:s') }} UTC.</p>
                <p>Autor autenticado: {{ $revision->actor_snapshot ? $revision->actor_snapshot['nombre'].' '.($revision->actor_snapshot['apellidos'] ?? '').' (ID '.$revision->actor_id.')' : 'Desconocido en el registro antiguo' }}.</p>
                <p>Motivo: {{ $revision->motivo ?? 'No indicado' }}.</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if($revision->origin !== 'legacy_import')
                        <section><h4 class="font-bold mb-3">Valores anteriores</h4>@include('admin.cuadre.partials.revision-snapshot', ['snapshot' => $revision->before_snapshot])</section>
                    @endif
                    <section><h4 class="font-bold mb-3">{{ $revision->origin === 'legacy_import' ? 'Valores originales' : 'Valores guardados' }}</h4>@include('admin.cuadre.partials.revision-snapshot', ['snapshot' => $revision->snapshot])</section>
                </div>
            </article>
        @endforeach
        {{ $revisions->links() }}
    </div>
</x-app-layout>
