<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
                {{ __('Informes de Registro de Jornada') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-700">
                <div class="p-8">
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Generar Documento de Inspección</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Selecciona el periodo y los empleados para generar el informe oficial de registro diario de jornada (entradas y salidas).
                        </p>
                    </div>

                    <form action="{{ route('admin.registro-jornada.informe') }}" method="GET" target="_blank" class="space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Fecha Inicio -->
                            <div>
                                <label for="fecha_inicio" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" required value="{{ \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}"
                                    class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <!-- Fecha Fin -->
                            <div>
                                <label for="fecha_fin" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Fecha Fin</label>
                                <input type="date" name="fecha_fin" id="fecha_fin" required value="{{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}"
                                    class="w-full rounded-xl border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="pt-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-4 justify-end">
                            <button type="submit" name="tipo_informe" value="normal" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Informe Oficial
                            </button>
                            
                            <button type="submit" name="tipo_informe" value="resumido" class="px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl shadow-md transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Informe Resumido
                            </button>
                            
                            <button type="submit" name="tipo_informe" value="fichaje" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                Fichajes Detallados
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
