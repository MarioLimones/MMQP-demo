<div class="p-6 grid grid-cols-1 {{ $cuadre->turno === 'cierre' ? 'lg:grid-cols-2' : '' }} gap-8 border-t border-slate-200 dark:border-slate-700">
    
    @if($cuadre->turno === 'cierre')
    <!-- Lo que se ingresa -->
    <div class="bg-blue-50 dark:bg-blue-900/10 p-5 rounded-xl border border-blue-100 dark:border-blue-900/30">
        <h4 class="font-bold text-blue-800 dark:text-blue-400 mb-4 flex items-center gap-2 border-b border-blue-200 dark:border-blue-800/50 pb-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Ingresado
        </h4>
        
        <div class="space-y-4">
            <!-- Billetes Ingreso -->
            <div>
                <h5 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Billetes</h5>
                <div class="grid grid-cols-5 gap-2 text-center text-xs">
                    @foreach($billetesVal as $val)
                        @if(!empty($b_i[$val]) && $b_i[$val] > 0)
                            <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-blue-100 dark:border-slate-700">
                                <div class="text-slate-500 font-medium">{{ $val }}€</div>
                                <div class="font-bold text-blue-600 dark:text-blue-400">x{{ $b_i[$val] }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            
            <!-- Monedas Ingreso -->
            <div>
                <h5 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Monedas</h5>
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 text-center text-xs">
                    @foreach($monedasVal as $val)
                        @if(!empty($m_i[(string)$val]) && $m_i[(string)$val] > 0)
                            <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-blue-100 dark:border-slate-700">
                                <div class="text-slate-500 font-medium">{{ $val < 1 ? ($val*100).'c' : $val.'€' }}</div>
                                <div class="font-bold text-blue-600 dark:text-blue-400">x{{ $m_i[(string)$val] }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="pt-3 border-t border-blue-200 dark:border-blue-800/50 flex justify-between font-bold text-blue-900 dark:text-blue-300">
                <span>Total Ingreso:</span>
                <span>{{ number_format($cuadre->ingreso_real ?? 0, 2) }} €</span>
            </div>
        </div>
    </div>
    @endif

    <!-- Lo que queda en caja (Fondo) -->
    <div class="bg-green-50 dark:bg-green-900/10 p-5 rounded-xl border border-green-100 dark:border-green-900/30">
        <h4 class="font-bold text-green-800 dark:text-green-400 mb-4 flex items-center gap-2 border-b border-green-200 dark:border-green-800/50 pb-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
            {{ $cuadre->turno === 'cierre' ? 'Queda en Caja (Fondo)' : 'En Caja / Fondo Fijo' }}
        </h4>
        
        <div class="space-y-4">
            <!-- Billetes Fondo -->
            <div>
                <h5 class="text-sm font-semibold text-green-700 dark:text-green-300 mb-2">Billetes</h5>
                <div class="grid grid-cols-5 gap-2 text-center text-xs">
                    @foreach($billetesVal as $val)
                        @php 
                            $q_b = (floatval($b[$val] ?? 0)) - (floatval($b_i[$val] ?? 0));
                        @endphp
                        @if($q_b > 0)
                            <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-green-100 dark:border-slate-700">
                                <div class="text-slate-500 font-medium">{{ $val }}€</div>
                                <div class="font-bold text-green-600 dark:text-green-400">x{{ $q_b }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            
            <!-- Monedas Fondo -->
            <div>
                <h5 class="text-sm font-semibold text-green-700 dark:text-green-300 mb-2">Monedas</h5>
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 text-center text-xs">
                    @foreach($monedasVal as $val)
                        @php 
                            $q_m = (floatval($m[(string)$val] ?? 0)) - (floatval($m_i[(string)$val] ?? 0));
                        @endphp
                        @if($q_m > 0)
                            <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-green-100 dark:border-slate-700">
                                <div class="text-slate-500 font-medium">{{ $val < 1 ? ($val*100).'c' : $val.'€' }}</div>
                                <div class="font-bold text-green-600 dark:text-green-400">x{{ $q_m }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Otros Fondo -->
            @if(floatval($bl['total'] ?? 0) > 0 || floatval($nota_blanca) > 0)
                <div>
                    <h5 class="text-sm font-semibold text-green-700 dark:text-green-300 mb-2">Otros</h5>
                    <div class="flex gap-4">
                        @if(floatval($bl['total'] ?? 0) > 0)
                            <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-yellow-200 dark:border-slate-700 text-xs text-center flex-1">
                                <div class="text-slate-500 font-medium">Blisters</div>
                                <div class="font-bold text-yellow-600 dark:text-yellow-500">{{ number_format($bl['total'], 2) }} €</div>
                            </div>
                        @endif
                        @if(floatval($nota_blanca) > 0)
                            <div class="bg-white dark:bg-slate-800 p-2 rounded shadow-sm border border-yellow-200 dark:border-slate-700 text-xs text-center flex-1">
                                <div class="text-slate-500 font-medium">Nota Blanca/Vales</div>
                                <div class="font-bold text-yellow-600 dark:text-yellow-500">{{ number_format($nota_blanca, 2) }} €</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="pt-3 border-t border-green-200 dark:border-green-800/50 flex justify-between font-bold text-green-900 dark:text-green-300">
                <span>Total en Caja:</span>
                <span>{{ number_format($cuadre->total_calculado - $cuadre->ingreso_real, 2) }} €</span>
            </div>
        </div>
    </div>
</div>
