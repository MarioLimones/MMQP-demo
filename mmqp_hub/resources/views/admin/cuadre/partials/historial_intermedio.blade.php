<div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8 border-t border-slate-200 dark:border-slate-700">
    
    <!-- Left Column: Caja Registradora & Otros Conceptos -->
    <div class="space-y-6">
        <!-- Caja Registradora (Monedas) -->
        <div class="bg-slate-50 dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700">
            <h4 class="font-bold text-slate-800 dark:text-slate-200 mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">
                1. Caja Registradora (Monedas)
            </h4>
            <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 text-center text-xs">
                @foreach($monedasVal as $val)
                    @if(!empty($m[(string)$val]) && $m[(string)$val] > 0)
                        <div class="bg-white dark:bg-slate-700 p-2 rounded shadow-sm border border-slate-200 dark:border-slate-600">
                            <div class="text-slate-500 dark:text-slate-400 font-medium">{{ $val < 1 ? ($val*100).'c' : $val.'€' }}</div>
                            <div class="font-bold text-slate-700 dark:text-slate-300">x{{ $m[(string)$val] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Conceptos Adicionales -->
        <div class="bg-slate-50 dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700">
            <h4 class="font-bold text-slate-800 dark:text-slate-200 mb-4 border-b border-slate-200 dark:border-slate-700 pb-2">
                2. Conceptos Adicionales
            </h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between p-2 bg-white dark:bg-slate-700 rounded border border-slate-200 dark:border-slate-600">
                    <span class="text-slate-600 dark:text-slate-400">Gastos:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($cuadre->gastos, 2) }} €</span>
                </div>
                <div class="flex justify-between p-2 bg-white dark:bg-slate-700 rounded border border-slate-200 dark:border-slate-600">
                    <span class="text-slate-600 dark:text-slate-400">Cambios:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($cuadre->cambios, 2) }} €</span>
                </div>
                <div class="flex justify-between p-2 bg-white dark:bg-slate-700 rounded border border-slate-200 dark:border-slate-600">
                    <span class="text-slate-600 dark:text-slate-400">Repartos:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($cuadre->repartos, 2) }} €</span>
                </div>
                <div class="flex justify-between p-2 bg-white dark:bg-slate-700 rounded border border-slate-200 dark:border-slate-600">
                    <span class="text-slate-600 dark:text-slate-400">Pedidos H.E.:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($cuadre->pedidos_hora_exacta, 2) }} €</span>
                </div>
                <div class="flex justify-between p-2 bg-white dark:bg-slate-700 rounded border border-slate-200 dark:border-slate-600 col-span-2">
                    <span class="text-slate-600 dark:text-slate-400">Otros:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($cuadre->otros, 2) }} €</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Caja Roja & Resumen -->
    <div class="space-y-6">
        <!-- Caja Roja -->
        <div class="bg-red-50 dark:bg-red-900/10 p-5 rounded-xl border border-red-100 dark:border-red-900/30">
            <h4 class="font-bold text-red-800 dark:text-red-400 mb-4 border-b border-red-200 dark:border-red-800/50 pb-2">
                3. Caja Roja
            </h4>
            <div class="space-y-4">
                <div>
                    <h5 class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2">Billetes</h5>
                    <div class="grid grid-cols-5 gap-2 text-center text-xs">
                        @foreach($billetesVal as $val)
                            @if(!empty($b[$val]) && $b[$val] > 0)
                                <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-red-100 dark:border-slate-700">
                                    <div class="text-slate-500 font-medium">{{ $val }}€</div>
                                    <div class="font-bold text-red-600 dark:text-red-400">x{{ $b[$val] }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                
                @if(floatval($bl['total'] ?? 0) > 0 || floatval($nota_blanca) > 0)
                    <div>
                        <h5 class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2">Blisters y Notas</h5>
                        <div class="flex gap-4">
                            @if(floatval($bl['total'] ?? 0) > 0)
                                <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-red-100 dark:border-slate-700 text-xs text-center flex-1">
                                    <div class="text-slate-500 font-medium">Blisters</div>
                                    <div class="font-bold text-red-600 dark:text-red-400">{{ number_format($bl['total'], 2) }} €</div>
                                </div>
                            @endif
                            @if(floatval($nota_blanca) > 0)
                                <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-red-100 dark:border-slate-700 text-xs text-center flex-1">
                                    <div class="text-slate-500 font-medium">Nota Blanca/Vales</div>
                                    <div class="font-bold text-red-600 dark:text-red-400">{{ number_format($nota_blanca, 2) }} €</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Resumen -->
        <div class="bg-blue-50 dark:bg-blue-900/10 p-5 rounded-xl border border-blue-100 dark:border-blue-900/30">
            <h4 class="font-bold text-blue-800 dark:text-blue-400 mb-4 border-b border-blue-200 dark:border-blue-800/50 pb-2">
                Resumen Intermedio
            </h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                    <span>Total Contado</span>
                    <span class="font-bold">{{ number_format($cuadre->total_calculado, 2) }} €</span>
                </div>
                <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                    <span>- Fondo Fijo</span>
                    <span class="font-bold">{{ number_format($cuadre->fondo_fijo, 2) }} €</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-blue-200 dark:border-blue-800/50">
                    <span class="font-bold text-blue-800 dark:text-blue-300">Subtotal</span>
                    <span class="font-black text-blue-600 dark:text-blue-400">{{ number_format($cuadre->total_calculado - $cuadre->fondo_fijo, 2) }} €</span>
                </div>
                <div class="flex justify-between items-center pt-4">
                    <span class="font-medium text-slate-600 dark:text-slate-400">Venta Parcial</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ number_format($cuadre->venta_parcial ?? 0, 2) }} €</span>
                </div>
                <div class="flex justify-between items-center pt-2 border-t border-blue-200 dark:border-blue-800/50 bg-white dark:bg-slate-800 p-2 rounded mt-2">
                    <span class="font-bold text-slate-800 dark:text-slate-200">Diferencia</span>
                    <span class="font-black {{ $cuadre->descuadre < 0 ? 'text-red-500' : ($cuadre->descuadre > 0 ? 'text-green-500' : 'text-slate-400') }}">
                        {{ $cuadre->descuadre > 0 ? '+' : '' }}{{ number_format($cuadre->descuadre, 2) }} €
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
