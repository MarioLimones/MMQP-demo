<div class="xl:col-span-2 space-y-6">
    <div class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
        <h3 class="font-bold text-lg mb-3">1. Caja registradora</h3>
        @include('admin.cuadre.partials.counts', ['kind' => 'monedas'])
    </div>
    <div class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
        <h3 class="font-bold text-lg mb-3">2. Conceptos adicionales declarados</h3>
        <p class="text-sm mb-4">Estas partidas se suman al total según la fórmula actual.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            @foreach(['gastos' => 'Gastos', 'cambios' => 'Cambios', 'repartos' => 'Repartos', 'pedidos_hora_exacta' => 'Pedidos hora exacta', 'otros' => 'Otros'] as $field => $label)
                <x-cuadre-money :field="$field" :label="$label.' (€)'" :value="$cuadre?->$field ?? '0.00'" />
            @endforeach
        </div>
    </div>
    <div class="bg-red-50 dark:bg-red-900/10 p-4 rounded-xl border border-red-200 dark:border-red-900">
        <h3 class="font-bold text-lg mb-3">3. Caja roja</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @include('admin.cuadre.partials.counts', ['kind' => 'billetes'])
            <div class="space-y-4">
                <x-cuadre-money field="blisters.total" label="Blisters, importe total (€)" :value="$cuadre?->blisters['total'] ?? '0.00'" />
                <x-cuadre-money field="nota_blanca" label="Nota blanca / vales (€)" :value="$cuadre?->nota_blanca ?? '0.00'" />
            </div>
        </div>
    </div>
</div>
<div class="space-y-6">
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-xl border border-blue-200 dark:border-blue-700">
        <x-cuadre-money field="venta_parcial" label="Venta parcial declarada (€)" :value="$cuadre?->venta_parcial ?? '0.00'" />
    </div>
    <div class="bg-slate-800 dark:bg-slate-900 p-5 rounded-xl text-white space-y-4">
        <h3 class="font-bold text-xl">Previsualización</h3>
        <p class="text-sm text-slate-300">Se confirmará al guardar. Las partidas y la venta parcial son declaraciones del empleado.</p>
        <div class="flex justify-between gap-2"><span>Total computable</span><span id="total-caja">0.00 €</span></div>
        <x-cuadre-money field="fondo_fijo" label="Fondo fijo esperado declarado (€)" :value="$cuadre?->fondo_fijo ?? '300.00'" />
        <div class="flex justify-between gap-2"><span>Subtotal</span><span id="subtotal-intermedio">0.00 €</span></div>
        <div class="flex justify-between gap-2"><span>Venta parcial</span><span id="venta-parcial-text">0.00 €</span></div>
        <div class="flex justify-between border-t border-slate-600 pt-3 gap-2"><span>Descuadre</span><span id="descuadre-text">0.00 €</span></div>
        <p class="text-sm text-slate-300" id="preview-state">Positivo: sobrante. Negativo: faltante.</p>
    </div>
    @include('admin.cuadre.partials.save')
</div>
