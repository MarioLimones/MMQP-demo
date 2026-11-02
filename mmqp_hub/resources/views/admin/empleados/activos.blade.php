<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-slate-800 dark:text-white leading-tight flex items-center gap-2">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                {{ __('Empleados Activos') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            @if($agrupados->isEmpty())
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-3xl p-16 flex flex-col items-center justify-center text-center shadow-sm">
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">No hay empleados activos</h3>
            </div>
            @else
                @php 
                    $colores = [
                        'repartidor'=>'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
                        'encargado'=>'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
                        'auxiliar'=>'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
                        'limpiadora'=>'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',

                        'oficina'=>'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300'
                    ]; 
                @endphp

                @foreach($agrupados as $rol => $emps)
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden mb-6">
                    <div class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 p-4 md:p-5 flex items-center gap-3">
                        <span class="text-sm font-bold uppercase tracking-wider px-3 py-1 rounded-lg {{ $colores[$rol] ?? 'bg-slate-200 text-slate-700' }}">
                            {{ ucfirst($rol) }}
                        </span>
                        <span class="text-slate-500 dark:text-slate-400 font-medium text-sm">{{ $emps->count() }} empleado(s)</span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse" style="font-size:14px;">
                            <thead>
                                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/50">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Empleado</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">DNI</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Teléfono</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Horas Contrato</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Fecha de Alta</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @foreach($emps as $emp)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold shadow-inner flex-shrink-0 bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                                {{ strtoupper(substr($emp->nombre, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-800 dark:text-white text-sm">{{ $emp->nombre }} {{ $emp->apellidos }}</div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $emp->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                        {{ $emp->dni ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-slate-700 dark:text-slate-300">
                                        {{ $emp->telefono ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-semibold text-slate-700 dark:text-slate-300 text-sm">{{ $emp->horas_mensuales ? rtrim(rtrim(number_format($emp->horas_mensuales, 1), '0'), '.') . 'h/mes' : '—' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-slate-700 dark:text-slate-300 text-sm">
                                        {{ $emp->fecha_inicio ? \Carbon\Carbon::parse($emp->fecha_inicio)->format('d/m/Y') : '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach
            @endif

        </div>
    </div>
</x-app-layout>
