<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Solicitudes (Días Libres)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('status'))
                <div class="mb-6 bg-emerald-500/20 border border-emerald-500 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="font-bold">{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 bg-rose-500/20 border border-rose-500 text-rose-700 dark:text-rose-300 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <ul class="list-disc list-inside text-sm font-medium">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section aria-labelledby="weekly-availability" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h3 id="weekly-availability" class="text-lg font-bold text-slate-800 dark:text-white">Disponibilidad semanal</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $user->tipo_disponibilidad === 'completa' ? 'Disponibilidad completa.' : 'Disponibilidad personalizada.' }}</p>
                @if($user->tipo_disponibilidad === 'personalizada')
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        @foreach(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $day)
                            @php($periods = in_array($day, $user->dias_disponibles ?? [], true) ? ['mañana', 'tarde'] : ($user->dias_disponibles[$day] ?? []))
                            <div class="flex justify-between gap-4"><dt class="font-semibold text-slate-700 dark:text-slate-200">{{ ucfirst($day) }}</dt><dd class="text-slate-600 dark:text-slate-300">{{ $periods ? implode(' y ', $periods) : 'No disponible' }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Disponibilidad en festivos</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ ['disponible' => 'Disponible para trabajar en los festivos configurados.', 'no_disponible' => 'No disponible para trabajar en los festivos configurados.'][$user->disponibilidad_festivos] ?? 'Se aplica tu disponibilidad semanal a los festivos configurados.' }}</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Contacta con tu responsable para actualizar tu disponibilidad.</p>
                </div>
            </section>

            <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-3xl overflow-hidden transition-colors duration-300 border border-slate-200 dark:border-slate-700 mb-8">
                <div class="p-6 sm:p-8">
                    
                    <div class="mb-6 border-b border-slate-200 dark:border-slate-700 pb-4">
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Solicitar Día Libre</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Rellena este formulario para solicitar un día puntual (asuntos propios, cita médica, etc).
                            El administrador recibirá una notificación y podrá aprobar o rechazar tu petición.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('profile.disponibilidad.update') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1">
                                <label for="absence-fecha-1" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">
                                    Fecha solicitada <span class="text-rose-500">*</span>
                                </label>
                                <input id="absence-fecha-1" type="date" name="fecha" value="{{ old('fecha') }}" required min="{{ date('Y-m-d') }}"
                                       class="w-full bg-slate-50 dark:bg-slate-900/80 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all shadow-inner">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="absence-motivo_empleado-2" class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-2">
                                    Motivo (Opcional)
                                </label>
                                <input id="absence-motivo_empleado-2" type="text" name="motivo_empleado" value="{{ old('motivo_empleado') }}" placeholder="Ej: Visita al médico a las 11:30..."
                                       class="w-full bg-slate-50 dark:bg-slate-900/80 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all shadow-inner">
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" class="px-6 py-3 bg-sky-700 hover:bg-sky-800 text-white font-bold rounded-xl transition-colors shadow-lg shadow-sky-500/30 whitespace-nowrap flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                Enviar Solicitud
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historial de Solicitudes -->
            <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-3xl overflow-hidden transition-colors duration-300 border border-slate-200 dark:border-slate-700">
                <div class="p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-6">Historial de Peticiones</h3>
                    
                    @if($solicitudes->isEmpty())
                        <div class="text-center py-8 bg-slate-50 dark:bg-slate-900/30 rounded-2xl border border-slate-200 dark:border-slate-700/50">
                            <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-slate-500 dark:text-slate-400 font-medium">No has solicitado ningún día libre todavía.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700">
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Motivo</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Estado</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Respuesta Admin</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                                    @foreach($solicitudes as $solicitud)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20 transition-colors">
                                            <td class="py-4 px-4 whitespace-nowrap">
                                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                                    {{ $solicitud->fecha->format('d/m/Y') }}
                                                    @if ($solicitud->fecha_fin && $solicitud->fecha_fin->gt($solicitud->fecha))
                                                        – {{ $solicitud->fecha_fin->format('d/m/Y') }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-sm text-slate-600 dark:text-slate-400">
                                                <span class="block font-semibold">{{ ucfirst(str_replace('_', ' ', $solicitud->tipo)) }}</span>
                                                {{ $solicitud->motivo_empleado ?: '-' }}
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                @if($solicitud->estado === 'pendiente')
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                                                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        Pendiente
                                                    </span>
                                                @elseif($solicitud->estado === 'aprobada')
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        Aprobada
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        {{ ucfirst($solicitud->estado) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-4 px-4 text-sm text-slate-600 dark:text-slate-400 italic">
                                                {{ $solicitud->razon_admin ?: '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
