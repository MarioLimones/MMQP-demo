@php
    $denominations = $kind === 'billetes' ? \App\Services\CuadreCalculator::BILLETES : \App\Services\CuadreCalculator::MONEDAS;
    $counts = old($kind, $cuadre?->$kind ?? []);
    $incomeField = $kind.'_ingreso';
    $incomeCounts = old($incomeField, $cuadre?->$incomeField ?? []);
    $unitLabel = $kind === 'billetes' ? 'Billetes' : 'Monedas';
@endphp
<fieldset class="min-w-0">
    <legend class="font-bold text-lg mb-3">{{ $unitLabel }}</legend>
    <table class="w-full text-sm">
        <thead><tr class="border-b border-slate-300 dark:border-slate-600">
            <th scope="col" class="text-left py-2">Valor</th><th scope="col">Total</th>
            @if($turno === 'cierre')<th scope="col">Al ingreso</th><th scope="col">Queda</th>@endif
        </tr></thead>
        <tbody>
            @foreach($denominations as $key => $cents)
                <tr class="border-b border-slate-200 dark:border-slate-700">
                    <th scope="row" class="text-left py-2 font-medium">{{ \App\Services\CuadreCalculator::decimal($cents) }} €</th>
                    <td class="text-center p-1">
                        <label class="sr-only" for="{{ $kind }}-{{ $key }}">{{ $unitLabel }} de {{ \App\Services\CuadreCalculator::decimal($cents) }} €, total</label>
                        <input id="{{ $kind }}-{{ $key }}" type="number" required min="0" max="999999" step="1"
                            name="{{ $kind }}[{{ $key }}]" value="{{ $counts[$key] ?? 0 }}" data-cents="{{ $cents }}"
                            aria-invalid="{{ $errors->has($kind) ? 'true' : 'false' }}" aria-describedby="error-{{ $kind }}"
                            class="calc-input {{ $kind === 'billetes' ? 'billete' : 'moneda' }}-total w-16 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-center p-1">
                    </td>
                    @if($turno === 'cierre')
                        <td class="text-center p-1">
                            <label class="sr-only" for="{{ $incomeField }}-{{ $key }}">{{ $unitLabel }} de {{ \App\Services\CuadreCalculator::decimal($cents) }} €, al ingreso</label>
                            <input id="{{ $incomeField }}-{{ $key }}" type="number" required min="0" max="999999" step="1"
                                name="{{ $incomeField }}[{{ $key }}]" value="{{ $incomeCounts[$key] ?? 0 }}" data-cents="{{ $cents }}"
                                aria-invalid="{{ $errors->has($incomeField) ? 'true' : 'false' }}" aria-describedby="error-{{ $incomeField }} error-{{ $incomeField }}-{{ $key }}"
                                class="calc-input {{ $kind === 'billetes' ? 'billete' : 'moneda' }}-ingreso w-16 rounded border-blue-300 dark:border-blue-600 dark:bg-slate-800 dark:text-white text-center p-1">
                        </td>
                        <td class="text-center {{ $kind === 'billetes' ? 'billete' : 'moneda' }}-fondo">0</td>
                    @endif
                </tr>
                @if($turno === 'cierre')
                    <tr hidden data-income-error-row>
                        <td colspan="4" class="pb-2 text-sm text-red-600 dark:text-red-400">
                            <p id="error-{{ $incomeField }}-{{ $key }}" aria-live="polite"></p>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    <p id="error-{{ $kind }}" class="text-sm text-red-600 dark:text-red-400 mt-2">{{ $errors->first($kind) }}</p>
    @if($turno === 'cierre')<p id="error-{{ $incomeField }}" class="text-sm text-red-600 dark:text-red-400">{{ $errors->first($incomeField) }}</p>@endif
    <div class="flex flex-wrap justify-between gap-2 mt-3 text-sm font-bold">
        <span>Total: <span id="suma-{{ $kind }}-total">0.00 €</span></span>
        @if($turno === 'cierre')
            <span>Ingreso: <span id="suma-{{ $kind }}-ingreso">0.00 €</span></span>
            <span>Queda: <span id="suma-{{ $kind }}-fondo">0.00 €</span></span>
        @endif
    </div>
</fieldset>
