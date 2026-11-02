<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a aria-label="Volver a empleados" href="{{ route('admin.empleados') }}" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight flex items-center gap-2">
                Buzón de Solicitudes
                @php $pendientes = $solicitudes->where('estado', 'pendiente')->count(); @endphp
                @if($pendientes > 0)
                    <span class="bg-amber-500 text-white font-bold px-2 py-0.5 rounded-full text-xs ml-2">{{ $pendientes }}</span>
                @endif
            </h2>
        </div>
    </x-slot>

    <div class="py-12" x-data="solicitudesAdmin()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('components.absence-feedback')
            
            @if (session('success'))
                <div class="mb-6 bg-emerald-500/20 border border-emerald-500 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="font-bold">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-3xl overflow-hidden transition-colors duration-300 border border-slate-200 dark:border-slate-700">
                <div class="p-6 sm:p-8">
                    
                    @if($solicitudes->isEmpty())
                        <div class="text-center py-12 bg-slate-50 dark:bg-slate-900/30 rounded-2xl border border-slate-200 dark:border-slate-700/50">
                            <svg class="mx-auto h-16 w-16 text-slate-300 dark:text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-1">El buzón está vacío</h3>
                            <p class="text-slate-500 dark:text-slate-400">Tus empleados no han solicitado días libres recientemente.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Empleado</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha Solicitada</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Motivo</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Estado</th>
                                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                                    @foreach($solicitudes as $solicitud)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20 transition-colors {{ $solicitud->estado === 'pendiente' ? 'bg-amber-50/30 dark:bg-amber-900/10' : '' }}">
                                            <td class="py-4 px-4 whitespace-nowrap">
                                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                                    {{ $solicitud->user->nombre }} {{ $solicitud->user->apellidos }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ ucfirst($solicitud->user->rol) }}
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 whitespace-nowrap">
                                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                                    {{ $solicitud->fecha->format('d/m/Y') }}
                                                    @if ($solicitud->fecha_fin && $solicitud->fecha_fin->gt($solicitud->fecha))
                                                        – {{ $solicitud->fecha_fin->format('d/m/Y') }}
                                                    @endif
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ ucfirst($solicitud->fecha->translatedFormat('l')) }}
                                                    · {{ ucfirst(str_replace('_', ' ', $solicitud->tipo)) }}
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-sm text-slate-600 dark:text-slate-400 max-w-xs truncate" title="{{ $solicitud->motivo_empleado }}">
                                                {{ $solicitud->motivo_empleado ?: '-' }}
                                            </td>
                                            <td class="py-4 px-4">
                                                @if($solicitud->estado === 'pendiente')
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        Pendiente
                                                    </span>
                                                @elseif($solicitud->estado === 'aprobada')
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        Aprobada
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        {{ ucfirst($solicitud->estado) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-4 px-4 text-right">
                                                @if($solicitud->estado === 'pendiente')
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button @click="abrirModal({{ $solicitud->id }}, 'aprobada', '{{ $solicitud->user->nombre }}', '{{ $solicitud->fecha->format('d/m/Y') }}', {{ $solicitud->version }})" class="p-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 dark:bg-emerald-500/20 dark:hover:bg-emerald-500/30 dark:text-emerald-300 rounded-lg transition-colors" title="Aprobar">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        </button>
                                                        <button @click="abrirModal({{ $solicitud->id }}, 'rechazada', '{{ $solicitud->user->nombre }}', '{{ $solicitud->fecha->format('d/m/Y') }}', {{ $solicitud->version }})" class="p-2 bg-rose-100 hover:bg-rose-200 text-rose-700 dark:bg-rose-500/20 dark:hover:bg-rose-500/30 dark:text-rose-300 rounded-lg transition-colors" title="Rechazar">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-xs text-slate-400 italic">Resuelta</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            {{ $solicitudes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Modal de Respuesta -->
        <div x-show="modalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay -->
                <div x-show="modalOpen" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 transition-opacity" aria-hidden="true" @click="modalOpen = false">
                    <div class="absolute inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <!-- Modal Panel -->
                <div x-show="modalOpen" 
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-slate-200 dark:border-slate-700">
                    
                    <form x-bind:action="'{{ route('admin.solicitudes.update', 'ID_PLACEHOLDER') }}'.replace('ID_PLACEHOLDER', solicitudId)" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="estado" :value="accion">
                        <input type="hidden" name="version" :value="version">
                        
                        <div class="px-6 py-6 sm:px-8 sm:py-8">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="p-3 rounded-2xl" :class="accion === 'aprobada' ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600' : 'bg-rose-100 dark:bg-rose-500/20 text-rose-600'">
                                    <svg x-show="accion === 'aprobada'" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <svg x-show="accion === 'rechazada'" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-black text-slate-800 dark:text-white" x-text="accion === 'aprobada' ? 'Aprobar Solicitud' : 'Rechazar Solicitud'"></h3>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                        Para <span class="font-bold text-slate-700 dark:text-slate-300" x-text="nombreEmp"></span> el <span class="font-bold text-slate-700 dark:text-slate-300" x-text="fechaReq"></span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="absence-razon_admin-1" class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Mensaje o razón (Opcional)</label>
                                <textarea id="absence-razon_admin-1" name="razon_admin" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all" :class="accion === 'aprobada' ? 'focus:ring-emerald-500' : 'focus:ring-rose-500'" :placeholder="accion === 'aprobada' ? 'Ej: Todo correcto, disfruta tu día.' : 'Ej: Lo siento, ese día ya hay mucha gente librando.'"></textarea>
                                
                                <p x-show="accion === 'aprobada'" class="mt-3 text-xs text-emerald-600 dark:text-emerald-400 font-medium">Al aprobar, este día se añadirá automáticamente a las fechas bloqueadas del empleado para que la IA no le asigne turnos.</p>
                                <p x-show="accion === 'rechazada'" class="mt-3 text-xs text-rose-600 dark:text-rose-400 font-medium">Al rechazar, esta solicitud deja de bloquear fechas. Otras ausencias o restricciones vigentes seguirán aplicándose.</p>
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-200 dark:border-slate-700 sm:flex sm:flex-row-reverse gap-3 rounded-b-3xl">
                            <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 font-bold text-white rounded-xl shadow-lg transition-colors" :class="accion === 'aprobada' ? 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-500/30' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-500/30'">
                                Confirmar Acción
                            </button>
                            <button type="button" @click="modalOpen = false" class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-xl border border-slate-300 dark:border-slate-600 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('solicitudesAdmin', () => ({
                modalOpen: false,
                solicitudId: '',
                accion: '',
                nombreEmp: '',
                fechaReq: '',
                version: '',
                
                abrirModal(id, accion, nombre, fecha, version) {
                    this.solicitudId = id;
                    this.accion = accion;
                    this.nombreEmp = nombre;
                    this.fechaReq = fecha;
                    this.version = version;
                    this.modalOpen = true;
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>
