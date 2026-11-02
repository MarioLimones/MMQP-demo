<div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 dark:bg-slate-700/50 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
    @include('admin.cuadre.partials.counts', ['kind' => 'billetes'])
    @include('admin.cuadre.partials.counts', ['kind' => 'monedas'])
</div>
<div class="space-y-6">
    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-xl border border-yellow-200 dark:border-yellow-700 space-y-4">
        <x-cuadre-money field="blisters.total" label="Blisters, importe total (€)" :value="$cuadre?->blisters['total'] ?? '0.00'" />
        <x-cuadre-money field="nota_blanca" label="Nota blanca / vales (€)" :value="$cuadre?->nota_blanca ?? '0.00'" />
    </div>
    <div class="bg-slate-800 dark:bg-slate-900 p-5 rounded-xl text-white space-y-4">
        <h3 class="font-bold text-xl">Previsualización</h3>
        <p class="text-sm text-slate-300">Se confirmará al guardar. El fondo y las partidas son declaraciones del empleado.</p>
        <div class="flex justify-between gap-2"><span>Total computable</span><span id="total-caja">0.00 €</span></div>
        @if($turno === 'cierre')
            <div class="flex justify-between gap-2"><span>Total al ingreso</span><span id="total-ingreso-text">0.00 €</span></div>
            <div class="flex justify-between gap-2"><span>Se queda en caja</span><span id="total-fondo-text">0.00 €</span></div>
        @endif
        <x-cuadre-money field="fondo_fijo" label="Fondo fijo esperado declarado (€)" :value="$cuadre?->fondo_fijo ?? '300.00'" />
        <div class="flex justify-between border-t border-slate-600 pt-3 gap-2"><span>Descuadre</span><span id="descuadre-text">0.00 €</span></div>
        <p class="text-sm text-slate-300" id="preview-state">Positivo: sobrante. Negativo: faltante.</p>
    </div>
    @include('admin.cuadre.partials.save')
</div>
