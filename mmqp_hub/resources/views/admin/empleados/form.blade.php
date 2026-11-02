<x-app-layout>
@php
    $isEdit  = !is_null($empleado);
    $action  = $isEdit ? route('admin.empleados.actualizar', $empleado->id) : route('admin.empleados.guardar');
    $dias    = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
    $disp    = old('dias_disponibles', $isEdit ? ($empleado->dias_disponibles ?? []) : []);
    $disp = is_array($disp) ? $disp : (is_string($disp) ? json_decode($disp, true) ?? [] : []);

    $tipoDisp = old('tipo_disponibilidad', $empleado?->tipo_disponibilidad ?? 'completa');
@endphp

<div class="employee-profile" x-data="empleadoForm()">
    <header class="employee-profile__heading">
        <div>
            <h1>{{ $isEdit ? 'Editar empleado' : 'Nuevo empleado' }}</h1>
            <p>{{ $isEdit ? trim($empleado->nombre.' '.($empleado->apellidos ?? '')) : 'Añade los datos personales, el contrato y la disponibilidad.' }}</p>
        </div>
        <a href="{{ route('admin.empleados') }}" class="employee-profile__back">
            <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
            Empleados
        </a>
    </header>
    <form method="POST" action="{{ $action }}" class="space-y-6" @submit="submitting = true">
        @csrf
        @if($isEdit) @method('PUT') @endif

        @if($errors->any())
            <div role="alert" tabindex="-1" class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400 font-bold mb-2">
                    <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Hay errores en el formulario:
                </div>
                <ul class="list-disc list-inside text-sm text-rose-700 dark:text-rose-300/80 ml-2">
                    @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
        @endif

        <!-- TARJETA 1: Información personal -->
        <div class="employee-profile__card">
            <div class="employee-profile__section-heading">
                <div class="p-2 bg-red-100 dark:bg-red-500/20 rounded-lg text-red-600 dark:text-red-400">
                    <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">Información personal</h2>
            </div>
            
            <div class="employee-profile__section-body grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Nombre -->
                <div>
                    <label for="empleado-nombre" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Nombre <span class="text-rose-600 dark:text-rose-400">*</span></label>
                    <input type="text" autocomplete="given-name" id="empleado-nombre" name="nombre" value="{{ old('nombre', $empleado?->nombre) }}" required
                           class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                    @error('nombre') <div class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</div> @enderror
                </div>
                
                <!-- Apellidos -->
                <div>
                    <label for="empleado-apellidos" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Apellidos</label>
                    <input type="text" autocomplete="family-name" id="empleado-apellidos" name="apellidos" value="{{ old('apellidos', $empleado?->apellidos) }}"
                           class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                </div>
                
                <!-- DNI -->
                <div>
                    <label for="empleado-dni" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">DNI</label>
                    <input type="text" id="empleado-dni" name="dni" value="{{ old('dni', $empleado?->dni) }}" placeholder="12345678A"
                           class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                    @error('dni') <div class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</div> @enderror
                </div>
                
                <!-- Teléfono -->
                <div>
                    <label for="empleado-telefono" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Teléfono</label>
                    <input type="tel" autocomplete="tel" id="empleado-telefono" name="telefono" value="{{ old('telefono', $empleado?->telefono) }}" placeholder="600 123 456"
                           class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                </div>
                
                <!-- Email, Password -->
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 p-4 bg-white dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl">
                    <div>
                        <label for="empleado-email" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Email para inicio de sesión</label>
                        <input type="email" autocomplete="email" id="empleado-email" name="email" value="{{ old('email', $empleado?->email) }}"
                               class="w-full bg-slate-50 dark:bg-slate-900/80 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                        @error('email') <div class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="empleado-password" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">{{ $isEdit ? 'Nueva contraseña (vacío no cambia)' : 'Contraseña de acceso' }}</label>
                        <input type="password" autocomplete="new-password" id="empleado-password" name="password"
                               class="w-full bg-slate-50 dark:bg-slate-900/80 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                        @error('password') <div class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- TARJETA 2: Rol, Contrato y Permisos -->
        <div class="employee-profile__card">
            <div class="employee-profile__section-heading">
                <div class="p-2 bg-amber-100 dark:bg-amber-500/20 rounded-lg text-amber-600 dark:text-amber-400">
                    <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">Contrato y condiciones laborales</h2>
            </div>
            
            <div class="employee-profile__section-body grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Rol -->
                <div>
                    <label for="empleado-rol" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Rol <span class="text-rose-600 dark:text-rose-400">*</span></label>
                    <div class="relative">
                        <select id="empleado-rol" name="rol" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl pl-4 pr-10 py-3 text-slate-700 dark:text-slate-200 appearance-none focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all cursor-pointer">
                            @foreach(\App\Models\User::EMPLOYEE_ROLES as $r)
                                <option value="{{ $r }}" {{ old('rol', $empleado?->rol) == $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                            <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                    @error('rol') <div class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</div> @enderror
                </div>
                
                <!-- Horas Mensuales -->
                <div>
                    <label for="empleado-horas_mensuales" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Horas mensuales de contrato</label>
                    <div class="relative">
                        <input type="number" id="empleado-horas_mensuales" name="horas_mensuales" value="{{ old('horas_mensuales', $empleado?->horas_mensuales) }}" step="0.5" min="0" placeholder="160"
                               class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl pl-4 pr-10 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-500 font-bold">h</div>
                    </div>
                    @error('horas_mensuales') <div class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</div> @enderror
                </div>
                
                <fieldset class="md:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-6 border border-slate-200 dark:border-slate-700 rounded-2xl p-4">
                    <legend class="px-2 text-sm font-bold text-slate-800 dark:text-slate-100">Información contractual</legend>
                    <div>
                    <label for="empleado-fecha_inicio" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Fecha de inicio del contrato</label>
                    <input type="date" id="empleado-fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio', $empleado?->fecha_inicio?->format('Y-m-d')) }}"
                           @error('fecha_inicio') aria-describedby="empleado-fecha_inicio-error" aria-invalid="true" @enderror
                           class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                    @error('fecha_inicio') <p id="empleado-fecha_inicio-error" class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="empleado-fecha_fin_contrato" class="block text-sm font-bold text-slate-600 dark:text-slate-300 mb-2">Fecha de finalización del contrato</label>
                        <input type="date" id="empleado-fecha_fin_contrato" name="fecha_fin_contrato" value="{{ old('fecha_fin_contrato', $empleado?->fecha_fin_contrato?->format('Y-m-d')) }}"
                               aria-describedby="empleado-fecha_fin_contrato-help{{ $errors->has('fecha_fin_contrato') ? ' empleado-fecha_fin_contrato-error' : '' }}" @error('fecha_fin_contrato') aria-invalid="true" @enderror
                               class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                        <p id="empleado-fecha_fin_contrato-help" class="mt-2 text-sm text-slate-600 dark:text-slate-400">Déjala vacía si el contrato es indefinido o no hay fecha de finalización registrada.</p>
                        @error('fecha_fin_contrato') <p id="empleado-fecha_fin_contrato-error" class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                </fieldset>
                
                <!-- Permiso Admin -->
                <div class="bg-fuchsia-50 dark:bg-fuchsia-900/10 border border-fuchsia-200 dark:border-fuchsia-500/20 rounded-2xl p-4 flex flex-col justify-center">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="es_admin" value="1" class="peer sr-only" {{ old('es_admin', $empleado?->es_admin) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-fuchsia-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-fuchsia-500"></div>
                        </div>
                        <div>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Privilegios de Admin</span>
                            <span class="block text-xs text-fuchsia-700 dark:text-fuchsia-300">Otorga acceso total a este panel</span>
                        </div>
                    </label>
                </div>

                <!-- Menor de Edad -->
                <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 rounded-2xl p-4 flex flex-col justify-center">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="es_menor" value="1" class="peer sr-only" {{ old('es_menor', $empleado?->es_menor) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </div>
                        <div>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Menor de Edad</span>
                            <span class="block text-xs text-amber-800 dark:text-amber-300">Restricciones de horario nocturno</span>
                        </div>
                    </label>
                </div>

                <!-- Estado Laboral -->
                <div class="bg-rose-50 dark:bg-rose-900/10 border border-rose-200 dark:border-rose-500/20 rounded-2xl p-4">
                    <label for="empleado-estado-laboral" class="block text-sm font-bold text-slate-800 dark:text-white mb-2">Estado Laboral</label>
                    <select id="empleado-estado-laboral" x-model="estadoLaboral" class="w-full bg-white dark:bg-slate-900/50 border border-rose-300 dark:border-rose-600 rounded-xl px-3 py-2 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-all cursor-pointer text-sm">
                        <option value="activo">Activo</option>
                        <option value="Baja médica">Baja Médica</option>
                        <option value="Baja laboral">Baja Laboral</option>
                    </select>
                    
                    <input type="hidden" name="en_baja" :value="estadoLaboral === 'activo' ? '0' : '1'">
                    <input type="hidden" name="baja_motivo" :value="estadoLaboral !== 'activo' ? estadoLaboral : ''">

                    <div x-show="estadoLaboral !== 'activo'"  class="mt-3">
                        <label for="empleado-baja_desde" class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Desde fecha:</label>
                        <input type="date" id="empleado-baja_desde" name="baja_desde" value="{{ old('baja_desde', $empleado?->baja_desde?->format('Y-m-d')) }}"
                               class="w-full bg-white dark:bg-slate-900/50 border border-rose-300 dark:border-rose-600 rounded-xl px-3 py-1.5 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-all text-sm">
                    </div>
                </div>
            </div>
        </div>

        <!-- TARJETA 3: Disponibilidad y Días Libres -->
        <div class="employee-profile__card">
            <div class="employee-profile__section-heading">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-500/20 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-white">Disponibilidad</h2>
                </div>
            </div>
            
            <div class="employee-profile__section-body space-y-6">
                <!-- Selector de Disponibilidad -->
                <fieldset>
                    <legend class="block text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Disponibilidad semanal</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="relative cursor-pointer group">
                            <input type="radio" name="tipo_disponibilidad" value="completa" x-model="disponibilidad" class="peer sr-only" @checked($tipoDisp === 'completa')>
                            <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl peer-checked:bg-emerald-500/20 peer-checked:border-emerald-500 peer-checked:text-emerald-800 dark:peer-checked:text-emerald-300 text-slate-500 dark:text-slate-400 font-medium transition-colors text-center group-hover:border-slate-500 peer-focus-visible:ring-2 peer-focus-visible:ring-red-500 peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-slate-800">
                                Completa
                            </div>
                        </label>
                        <label class="relative cursor-pointer group">
                            <input type="radio" name="tipo_disponibilidad" value="personalizada" x-model="disponibilidad" class="peer sr-only" @checked($tipoDisp === 'personalizada')>
                            <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded-xl peer-checked:bg-red-500/20 peer-checked:border-red-500 peer-checked:text-red-800 dark:peer-checked:text-red-300 text-slate-500 dark:text-slate-400 font-medium transition-colors text-center group-hover:border-slate-500 peer-focus-visible:ring-2 peer-focus-visible:ring-red-500 peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-slate-800">
                                Personalizada
                            </div>
                        </label>
                    </div>
                </fieldset>

                <!-- Días que puede trabajar (Solo se muestra si es personalizada) -->
                <div x-show="disponibilidad === 'personalizada'" x-cloak class="space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-300">Selecciona los días y turnos en los que puede trabajar.</p>
                    <div class="employee-profile__week">
                        @foreach($dias as $dia)
                            @php
                                $selectedDia = isset($disp[$dia]) ? $disp[$dia] : [];
                                if (in_array($dia, $disp, true)) {
                                    $selectedDia = ['mañana', 'tarde'];
                                }
                                $diaLabel = ['miercoles' => 'Miércoles', 'sabado' => 'Sábado'][$dia] ?? ucfirst($dia);
                            @endphp
                            <fieldset class="employee-profile__day">
                                <legend>{{ $diaLabel }}</legend>
                                <div class="employee-profile__day-options">
                                    @foreach(['mañana' => 'Mañana', 'tarde' => 'Tarde'] as $turno => $turnoLabel)
                                        <label>
                                            <input type="checkbox" name="dias_disponibles[{{ $dia }}][]" value="{{ $turno }}" @checked(in_array($turno, $selectedDia))>
                                            <span>{{ $turnoLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </div>

                @include('admin.empleados.partials.holiday-availability')
            </div>
        </div>

        <!-- BOTONERA -->
        <div class="employee-profile__actions">
            <a href="{{ route('admin.empleados') }}" class="employee-profile__cancel">Cancelar</a>
            <button type="submit" class="employee-profile__save" :disabled="submitting">
                <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7" /></svg>
                <span x-show="!submitting">{{ $isEdit ? 'Guardar cambios' : 'Crear empleado' }}</span>
                <span x-show="submitting" x-cloak>Guardando…</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('empleadoForm', () => ({
            disponibilidad: @js($tipoDisp),
            submitting: false,
            estadoLaboral: '{{ old("en_baja", $empleado?->en_baja) ? (stripos(old("baja_motivo", $empleado?->baja_motivo), "laboral") !== false ? "Baja laboral" : "Baja médica") : "activo" }}',
        }));
    });
</script>
@endpush
</x-app-layout>
