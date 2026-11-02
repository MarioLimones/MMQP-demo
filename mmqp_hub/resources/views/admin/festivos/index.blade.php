<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Calendario de festivos</h1></x-slot>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
        <a href="{{ route('admin.empleados') }}" class="inline-flex min-h-11 items-center font-semibold text-indigo-700 dark:text-indigo-300">Volver a empleados</a>
        <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">Añade las fechas festivas del centro. La planificación usará la disponibilidad en festivos de cada empleado en estas fechas; el resto de días mantendrá la disponibilidad semanal.</p>
        @if(session('success'))<p role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">{{ session('success') }}</p>@endif
        @if($errors->any())
            <div role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-rose-800 dark:bg-rose-900/30 dark:text-rose-200">
                <p class="font-semibold">Revisa las fechas y nombres antes de guardar.</p>
                <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="{{ route('admin.festivos.update') }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800 sm:p-8">
            @csrf @method('PUT')
            <section aria-labelledby="configured-holidays">
                <h2 id="configured-holidays" class="mb-4 text-lg font-bold text-slate-800 dark:text-slate-100">Festivos configurados</h2>
                <div class="space-y-4">
                    @forelse(old('festivos', $festivos->map(fn ($festivo) => ['fecha' => $festivo->fecha->format('Y-m-d'), 'nombre' => $festivo->nombre])->all()) as $index => $festivo)
                        <div class="grid gap-3 border-b border-slate-200 pb-4 dark:border-slate-700 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
                            <div><label for="holiday-date-{{ $index }}" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Fecha</label><input id="holiday-date-{{ $index }}" type="date" name="festivos[{{ $index }}][fecha]" value="{{ $festivo['fecha'] ?? '' }}" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></div>
                            <div><label for="holiday-name-{{ $index }}" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nombre</label><input id="holiday-name-{{ $index }}" name="festivos[{{ $index }}][nombre]" value="{{ $festivo['nombre'] ?? '' }}" maxlength="120" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></div>
                            <label class="flex min-h-11 items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="festivos[{{ $index }}][eliminar]" value="1" @checked(!empty($festivo['eliminar'])) class="rounded text-rose-600 focus:ring-rose-500">Eliminar</label>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600 dark:text-slate-400">No hay festivos configurados. Añade una fecha para aplicar la disponibilidad en festivos.</p>
                    @endforelse
                </div>
            </section>
            <fieldset class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                <legend class="mb-3 text-lg font-bold text-slate-800 dark:text-slate-100">Añadir festivo</legend>
                <div><label for="new-holiday-date" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Fecha</label><input id="new-holiday-date" type="date" name="nuevo[fecha]" value="{{ old('nuevo.fecha') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></div>
                <div><label for="new-holiday-name" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nombre del festivo</label><input id="new-holiday-name" name="nuevo[nombre]" value="{{ old('nuevo.nombre') }}" maxlength="120" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></div>
            </fieldset>
            <button type="submit" class="min-h-11 w-full rounded-xl bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">Guardar calendario</button>
        </form>
    </div>
</x-app-layout>
