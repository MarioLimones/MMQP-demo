<div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
    <label for="dni_empleado" class="block text-sm font-medium mb-2">DNI del empleado responsable (ocho dígitos, sin letra)</label>
    @php
        $responsibleDni = $cuadre?->calculation_snapshot['responsible']['dni'] ?? $cuadre?->responsible?->dni ?? $cuadre?->user?->dni;
    @endphp
    <input type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" name="dni_empleado" id="dni_empleado" required
        value="{{ old('dni_empleado', $responsibleDni ? substr($responsibleDni, 0, 8) : '') }}"
        aria-invalid="{{ $errors->has('dni_empleado') ? 'true' : 'false' }}" aria-describedby="dni-help error-dni_empleado"
        class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
    <p id="error-dni_empleado" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $errors->first('dni_empleado') }}</p>
    <p id="dni-help" class="text-sm text-slate-600 dark:text-slate-300 mt-2">El responsable es declarado. La autoría de este guardado se registrará con tu sesión: {{ auth()->user()->nombre }}.</p>
    <label for="motivo" class="block text-sm font-medium mt-4 mb-1">Motivo de la rectificación (opcional)</label>
    <textarea name="motivo" id="motivo" maxlength="1000" rows="3" aria-describedby="error-motivo" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('motivo') }}</textarea>
    <p id="error-motivo" class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('motivo') }}</p>
</div>
<button id="save-cuadre" type="submit" @disabled(isset($conflict)) class="w-full py-3 bg-green-700 hover:bg-green-800 disabled:opacity-60 text-white font-bold rounded-lg shadow transition-colors">
    {{ $cuadre ? 'Guardar rectificación' : 'Guardar cuadre' }}
</button>
<p id="cuadre-save-feedback" hidden role="status" aria-live="polite" class="text-sm font-medium text-slate-700 dark:text-slate-300"></p>
