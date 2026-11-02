<fieldset aria-describedby="holiday-availability-help" class="space-y-3">
    <legend class="text-sm font-bold text-slate-700 dark:text-slate-200">Disponibilidad en festivos</legend>
    <p id="holiday-availability-help" class="text-sm text-slate-600 dark:text-slate-400">
        Se aplica a las fechas del calendario de festivos. Las vacaciones, ausencias y bajas siguen vigentes.
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @foreach(['semanal' => 'Usar disponibilidad semanal', 'disponible' => 'Disponible en festivos', 'no_disponible' => 'No disponible en festivos'] as $value => $label)
            <label class="flex min-h-12 items-center gap-3 rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-200 cursor-pointer">
                <input type="radio" name="disponibilidad_festivos" value="{{ $value }}" required
                       @checked(old('disponibilidad_festivos', $empleado?->disponibilidad_festivos ?? 'semanal') === $value)
                       class="shrink-0 text-indigo-600 focus:ring-indigo-500">
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('disponibilidad_festivos')<p class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>@enderror
    <a href="{{ route('admin.festivos.index') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-sm font-semibold text-indigo-700 underline underline-offset-4 dark:text-indigo-300">
        Configurar calendario de festivos (se abre en otra pestaña)
    </a>
</fieldset>
