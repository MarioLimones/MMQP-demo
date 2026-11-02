<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-800 dark:text-white leading-tight flex items-center gap-2">
            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ __('Cuadre de Caja') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-slate-50 dark:bg-slate-900 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            @if (session('status'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('status') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div id="error-summary" tabindex="-1" role="alert" class="bg-red-50 dark:bg-red-900/20 border border-red-300 p-4 rounded">
                    <h3 class="font-bold">Revisa los datos. El cuadre no se ha guardado.</h3>
                    <ul class="list-disc ml-5 mt-2">
                        @foreach($errors->messages() as $field => $messages)
                            @php
                                $target = ['billetes' => 'billetes-100', 'monedas' => 'monedas-2', 'billetes_ingreso' => 'billetes_ingreso-100', 'monedas_ingreso' => 'monedas_ingreso-2', 'blisters' => 'blisters-total', 'fecha' => 'filter-fecha', 'turno' => 'filter-turno', 'request_id' => 'cuadre-form', 'expected_version' => 'cuadre-form'][$field] ?? str_replace('.', '-', $field);
                            @endphp
                            @foreach($messages as $message)<li><a class="underline" href="#{{ $target }}">{{ $field }}: {{ $message }}</a></li>@endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(isset($conflict))
                <div id="cuadre-conflict" role="alert" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-400 p-4 rounded space-y-2">
                    <h3 class="font-bold">Conflicto de versión. Tus datos siguen en el formulario y no se han guardado.</h3>
                    <p>{{ $conflict }}</p>
                    @if($cuadre)<a class="underline mr-4" href="{{ route('admin.cuadre.revisiones', $cuadre) }}" target="_blank" rel="noopener">Comparar historial (nueva pestaña)</a>@endif
                    <a class="underline" href="{{ route('admin.cuadre', ['fecha' => $fecha, 'turno' => $turno]) }}">Cargar la versión vigente y descartar este borrador</a>
                </div>
            @endif

            <div class="bg-white dark:bg-slate-800 shadow sm:rounded-lg p-6">
                
                <form method="GET" action="{{ route('admin.cuadre') }}" class="mb-6 flex flex-wrap gap-4 items-end bg-slate-50 dark:bg-slate-700 p-4 rounded-lg border border-slate-200 dark:border-slate-600" id="filter-form">
                    <div>
                        <label for="filter-fecha" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha del Cuadre</label>
                        <input id="filter-fecha" type="date" name="fecha" value="{{ $fecha }}" required class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white shadow-sm focus:ring-green-500 focus:border-green-500 w-full">
                    </div>
                    <div>
                        <label for="filter-turno" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Turno</label>
                        <select id="filter-turno" name="turno" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white shadow-sm focus:ring-green-500 focus:border-green-500 w-full">
                            <option value="apertura" {{ $turno == 'apertura' ? 'selected' : '' }}>Apertura</option>
                            <option value="intermedio" {{ $turno == 'intermedio' ? 'selected' : '' }}>Intermedio</option>
                            <option value="cierre" {{ $turno == 'cierre' ? 'selected' : '' }}>Cierre</option>
                        </select>
                    </div>
                    <button class="px-4 py-2 rounded bg-slate-800 text-white" type="submit">Cargar cuadre</button>
                </form>

                <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">Introduce 0 cuando no haya unidades o importe. El servidor calcula el resultado al guardar; positivo significa sobrante y negativo, faltante.</p>
                <noscript><p class="mb-4">La previsualización requiere JavaScript. Puedes guardar y consultar el resultado calculado por el servidor.</p></noscript>

                <form method="POST" action="{{ route('admin.cuadre.store') }}" id="cuadre-form" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    @csrf
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                    <input type="hidden" name="turno" value="{{ $turno }}">
                    <input type="hidden" name="expected_version" value="{{ old('expected_version', $cuadre?->version ?? 0) }}">
                    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                    
                    @if($turno === 'intermedio')
                        @include('admin.cuadre.partials.form_intermedio')
                    @else
                        @include('admin.cuadre.partials.form_normal')
                    @endif
                </form>
            </div>
        </div>
    </div>

    @include('admin.cuadre.partials.scripts')
</x-app-layout>
