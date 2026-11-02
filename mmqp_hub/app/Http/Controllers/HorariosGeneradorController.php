<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\HorarioSemanal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\ScheduleDrafts;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;
use Illuminate\Validation\ValidationException;

class HorariosGeneradorController extends Controller
{
    public function generar(Request $request)
    {
        
        // El tiempo máximo lo recibimos en el payload (por defecto 2 minutos)
        $tiempo_maximo_minutos = $request->input('tiempo_maximo', 2);
        $tiempo_maximo_segundos = ($tiempo_maximo_minutos * 60) + 30; // 30s de margen
        
        // Ampliar el tiempo máximo de ejecución dinámicamente
        set_time_limit($tiempo_maximo_segundos);

        $request->validate([
            'metadatos'        => 'required|array',
            'plantilla_diseno' => 'required|array',
            'plantilla_diseno.*.fecha' => 'required|date_format:Y-m-d',
            'draft_id' => 'required|integer', 'version' => 'required|integer|min:1',
            'request_id' => 'required|uuid', 'metadatos.semana_id' => ['required', 'regex:/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/'],
            'ajustes_manuales' => 'sometimes|array', 'ajustes_manuales.*' => 'nullable|numeric|min:0|max:168',
        ]);

        app(ScheduleDrafts::class)->requireDraft((int) $request->draft_id, $request->metadatos['semana_id'], (int) $request->version);
        app(\App\Services\HolidayAssignments::class)->validate($request->plantilla_diseno, [], $request->metadatos['semana_id']);
        $empleados_frontend = [];

        // 1. Obtener todos los empleados de la BBDD (para mostrar bajas en frontend)
        $empleados_db = User::employees()->with(\App\Services\AbsenceAvailability::RELATIONS)->where('activo', '!=', false)
            ->orderBy('fecha_inicio', 'asc')
            ->orderBy('id', 'asc')
            ->get([
            'id', 'nombre', 'apellidos', 'rol', 'horas_mensuales', 'es_menor',
            'tipo_disponibilidad', 'disponibilidad_festivos', 'dias_bloqueados_recurrentes',
            'dias_disponibles', 'fechas_libres_solicitadas', 'en_baja', 'baja_motivo', 'vacaciones', 'orden_visual', 'fecha_inicio'
        ]);

        $empleados = [];
        $rolesRequeridos = collect($request->plantilla_diseno)->flatMap(fn ($dia) => $dia['turnos_requeridos'] ?? [])
            ->pluck('rol_necesario')->unique();
        $rolesSinHoras = [];
        $ajustes = $request->input('ajustes_manuales', []);
        foreach ($empleados_db as $emp) {
            $nombreCompleto = trim($emp->nombre . ' ' . $emp->apellidos);
            $empleado_data = [
                'id'                         => $emp->id,
                'nombre'                     => $nombreCompleto,
                'rol'                        => $emp->rol,
                'es_menor'                   => (bool) $emp->es_menor,
                'orden_visual'               => (int) $emp->orden_visual,
                'horas_mensuales'            => $emp->horas_mensuales ? (int) $emp->horas_mensuales : null,
                'tipo_disponibilidad'        => $emp->tipo_disponibilidad ?? 'completa',
                'disponibilidad_festivos'     => $emp->disponibilidad_festivos ?? 'semanal',
                'dias_bloqueados_recurrentes' => is_string($emp->dias_bloqueados_recurrentes)
                    ? json_decode($emp->dias_bloqueados_recurrentes, true) ?? []
                    : ($emp->dias_bloqueados_recurrentes ?? []),
                'fechas_libres_solicitadas'  => is_string($emp->fechas_libres_solicitadas)
                    ? json_decode($emp->fechas_libres_solicitadas, true) ?? []
                    : ($emp->fechas_libres_solicitadas ?? []),
                'dias_disponibles'           => is_string($emp->dias_disponibles)
                    ? json_decode($emp->dias_disponibles, true) ?? []
                    : ($emp->dias_disponibles ?? []),
                'en_baja'                    => (bool) $emp->en_baja,
                'baja_motivo'                => $emp->baja_motivo,
                'vacaciones'                 => is_string($emp->vacaciones)
                    ? json_decode($emp->vacaciones, true) ?? []
                    : ($emp->vacaciones ?? []),
            ];

            $tieneHoras = isset($ajustes[$emp->id]) && $ajustes[$emp->id] !== '';
            $empleado_data['horas_semanales_override'] = $tieneHoras ? (float) $ajustes[$emp->id] : 0.0;

            $empleados_frontend[] = $empleado_data;
            if (!$emp->en_baja) {
                $empleados[] = $empleado_data;
                if (!$tieneHoras && $rolesRequeridos->contains($emp->rol)) $rolesSinHoras[] = $emp->rol;
            }
        }

        if ($rolesSinHoras) {
            $requeridas = collect($request->plantilla_diseno)->flatMap(fn ($dia) => $dia['turnos_requeridos'] ?? [])
                ->groupBy('rol_necesario')->map(fn ($turnos) => $turnos->sum('duracion_horas'))->all();
            $disponibles = collect($empleados)->groupBy('rol')->map(fn ($rows) => $rows->sum('horas_semanales_override'))->all();
            $deficit = $exceso = [];
            foreach ($requeridas as $rol => $horas) {
                $deficit[$rol] = max(0, $horas - ($disponibles[$rol] ?? 0));
                $exceso[$rol] = max(0, ($disponibles[$rol] ?? 0) - $horas);
            }
            return response()->json([
                'success' => false, 'requires_confirmation' => true, 'requires_hour_input' => true,
                'roles_sin_horas' => array_values(array_unique($rolesSinHoras)),
                'message' => 'Introduce las horas semanales de cada trabajador o pulsa Autocompletar. Usa 0 para no asignarle horas.',
                'requeridas' => $requeridas, 'disponibles' => $disponibles, 'deficit' => $deficit, 'exceso' => $exceso,
                'empleados_disponibles' => $empleados,
            ]);
        }

        // 2. Construir JSON Payload
        $payload = [
            'metadatos'        => array_merge($request->metadatos, ['festivos' => app(\App\Services\HolidayCalendar::class)->dates()]),
            'empleados'        => array_values(array_filter($empleados, fn ($emp) => $emp['horas_semanales_override'] > 0)),
            'plantilla_diseno' => $request->plantilla_diseno,
            'ampliar_limites'  => $request->has('ampliar_limites') ? $request->boolean('ampliar_limites') : false,
            'tiempo_maximo'    => $tiempo_maximo_minutos
        ];

        // 3. Enviar Payload a la API de Python
        try {
            Log::info('--- ENVIANDO A PYTHON ---');
            file_put_contents(storage_path('logs/last_payload.json'), json_encode($payload, JSON_PRETTY_PRINT));

            $response = Http::timeout($tiempo_maximo_segundos + 10)->post('http://127.0.0.1:5000/generar', $payload);

            Log::info('--- RESPUESTA DE PYTHON ---');
            Log::info($response->body());

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success']) {
                    $resultados       = $data['resultados'];
                    $plantilla        = $payload['plantilla_diseno'];
                    $metadatos        = $payload['metadatos'];
                    $totalTurnos      = collect($plantilla)->sum(fn($d) => count($d['turnos_requeridos'] ?? []));
                    $turnosCubiertos  = count($resultados);

                    $horario = app(ScheduleDrafts::class)->save(
                        (int) $request->draft_id, $metadatos['semana_id'], (int) $request->version,
                        $request->request_id, [
                            'resultados' => $resultados, 'plantilla_diseno' => $plantilla,
                            'fecha_inicio' => $metadatos['fecha_inicio'], 'fecha_fin' => $metadatos['fecha_fin'],
                            'empleados_snapshot' => $empleados_frontend,
                        ]);
                    return response()->json([
                        'success' => true,
                        'redirect_url' => route('admin.horarios.resultado', ['id' => $horario->id]),
                    ]);

                } elseif (isset($data['requires_confirmation']) && $data['requires_confirmation']) {
                    return response()->json([
                        'success'              => false,
                        'requires_confirmation' => true,
                        'deficit'              => $data['deficit'] ?? [],
                        'exceso'               => $data['exceso'] ?? [],
                        'requeridas'           => $data['requeridas'] ?? [],
                        'disponibles'          => $data['disponibles'] ?? [],
                        'empleados_disponibles' => $empleados,
                        'message'              => $data['message'] ?? 'Se requiere confirmación.'
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => $data['message'] ?? 'Error desconocido desde la API de IA.'
                    ], 400);
                }
            } else {
                $data = $response->json();
                if (in_array($response->status(), [400, 404, 422], true)
                    && is_array($data) && ($data['success'] ?? null) === false
                    && is_string($data['message'] ?? null) && trim($data['message']) !== '') {
                    return response()->json([
                        'success' => false,
                        'message' => $data['message'],
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'El servidor de la IA devolvió un error (HTTP ' . $response->status() . ').',
                    'error'   => $response->body()
                ], $response->status());
            }
        } catch (HttpExceptionInterface | ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Fallo al conectar con la API Python: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con el servidor de la IA. ¿Está encendido el servidor Flask?',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function abrirBorrador(Request $request)
    {
        $input = $request->validate([
            'semana_id' => ['required', 'regex:/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/'],
            'source_id' => 'nullable|integer',
            'empresa_id' => 'prohibited', 'centro_id' => 'prohibited', 'equipo_id' => 'prohibited',
        ]);
        $draft = app(ScheduleDrafts::class)->open($input['semana_id'], $input['source_id'] ?? null);
        $viewDraft = $draft->toArray();
        $viewDraft['empleados_snapshot'] = app(\App\Services\AbsenceAvailability::class)->refreshSnapshot($draft->empleados_snapshot ?? []);
        return response()->json(['success' => true, 'draft' => $viewDraft]);
    }

    public function editarHorario($id)
    {
        $source = HorarioSemanal::findOrFail($id);
        $draft = app(ScheduleDrafts::class)->open($source->semana_id, $source->id);
        return redirect()->route('admin.horarios.resultado', $draft->id);
    }

    public function mostrarResultado($id)
    {
        $horario = HorarioSemanal::findOrFail($id);
        if (request()->has('semana')) {
            $target = HorarioSemanal::where('semana_id', request('semana'))
                ->when($horario->estado === 'historico', fn ($query) => $query->where('estado', 'historico'))
                ->orderByRaw("CASE WHEN estado = 'borrador' THEN 0 WHEN vigente = 1 THEN 1 ELSE 2 END")
                ->latest('id')->first();
            if ($target) {
                return redirect()->route('admin.horarios.resultado', $target->id);
            }
            return redirect()->route('admin.horarios.resultado', $id)->with('error', 'No hay horario para esa semana.');
        }
        $data = [
            'resultados' => $horario->resultados, 'plantilla' => $horario->plantilla_diseno,
            'metadatos' => ['semana_id' => $horario->semana_id, 'fecha_inicio' => $horario->fecha_inicio,
                'fecha_fin' => $horario->fecha_fin],
            'empleados' => $horario->estado === 'historico'
                ? ($horario->empleados_snapshot ?? [])
                : app(\App\Services\AbsenceAvailability::class)->refreshSnapshot($horario->empleados_snapshot ?? []),
            'roles_publicados' => ($horario->estado === 'borrador' && $horario->source_publicacion_id
                ? HorarioSemanal::find($horario->source_publicacion_id)?->roles_publicados : $horario->roles_publicados) ?? [],
            'horario_id' => $horario->id, 'version' => $horario->version, 'estado' => $horario->estado,
            'source_publicacion_id' => $horario->source_publicacion_id, 'vigente' => $horario->vigente,
            'published_draft_version' => $horario->published_draft_version,
            'is_importado' => $horario->is_importado,
            'is_transfer' => $horario->transfer_hash !== null,
            'festivos' => app(\App\Services\HolidayCalendar::class)->dates(),
        ];
        $es_borrador = $horario->estado === 'borrador';
        $data['absence_conflicts'] = app(\App\Services\PublishedAbsenceConflicts::class)->forSnapshot($data['empleados']);
        return view('admin.horarios-resultado', compact('data', 'id', 'es_borrador'));
    }

    private function draftInput(Request $request): array
    {
        $input = $request->validate([
            'draft_id' => 'required|integer', 'version' => 'required|integer|min:1',
            'request_id' => 'required|uuid',
            'semana_id' => ['required', 'regex:/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/'],
            'fecha_inicio' => 'required|string|max:100', 'fecha_fin' => 'required|string|max:100',
            'plantilla_diseno' => 'present|array', 'plantilla_diseno.*.dia' => 'required|string',
            'plantilla_diseno.*.turnos_requeridos' => 'present|array',
            'plantilla_diseno.*.turnos_requeridos.*.turno_id' => 'required|string|max:150',
            'plantilla_diseno.*.turnos_requeridos.*.rol_necesario' => 'required|in:encargado,repartidor,auxiliar,limpiadora,oficina',
            'plantilla_diseno.*.turnos_requeridos.*.hora_inicio' => 'required|date_format:H:i',
            'plantilla_diseno.*.turnos_requeridos.*.hora_fin' => 'required|date_format:H:i',
            'empleados_snapshot' => 'present|array', 'resultados' => 'sometimes|array',
            'resultados.*.turno_id' => 'required|string', 'resultados.*.empleado_id' => 'required',
            'estado' => 'prohibited', 'roles_publicados' => 'prohibited',
            'empresa_id' => 'prohibited', 'centro_id' => 'prohibited', 'equipo_id' => 'prohibited',
        ]);
        // Retain optional JSON metadata (notes, positions, local IDs and manual time edits).
        // Only these content arrays are copied; publication and identity fields remain server-controlled.
        return array_replace($input, $request->only(['plantilla_diseno', 'resultados', 'empleados_snapshot']));
    }

    public function guardarBorrador(Request $request)
    {
        $input = $this->draftInput($request);
        $draft = app(ScheduleDrafts::class)->save($input['draft_id'], $input['semana_id'],
            $input['version'], $input['request_id'], $input);
        return response()->json(['success' => true, 'draft' => $draft]);
    }

    public function guardarHorario(Request $request, $id)
    {
        return $this->actualizarHorario($request, $id);
    }

    public function actualizarHorario(Request $request, $id)
    {
        abort_unless((string) $request->input('draft_id') === (string) $id, 422);
        return $this->guardarBorrador($request);
    }

    public function publicarHorario(Request $request, $id)
    {
        $input = $request->validate([
            'semana_id' => 'required|string', 'version' => 'required|integer|min:1',
            'roles' => 'present|array', 'roles.*' => 'required|in:encargado,repartidor,auxiliar,limpiadora,oficina',
        ]);
        $published = app(ScheduleDrafts::class)->publish((int) $id, $input['semana_id'], $input['version'], $input['roles']);
        return response()->json(['success' => true, 'published' => $published,
            'redirect_url' => route('admin.horarios.resultado', $published->id)]);
    }

    public function historial()
    {
        $horarios = HorarioSemanal::with('generador')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function($horario) {
                try {
                    $dateStr = str_replace(
                        ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic', '.'], 
                        ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', ''], 
                        strtolower(trim($horario->fecha_inicio))
                    );
                    $date = \Carbon\Carbon::parse($dateStr);
                    if (!preg_match('/\d{4}/', $horario->fecha_inicio)) {
                        $date->year($horario->created_at ? $horario->created_at->year : date('Y'));
                    }
                    $horario->parsed_fecha_inicio = $date;
                } catch (\Exception $e) {
                    $horario->parsed_fecha_inicio = $horario->created_at ? $horario->created_at : now();
                }

                try {
                    if ($horario->fecha_fin) {
                        $dateStrEnd = str_replace(
                            ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic', '.'], 
                            ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', ''], 
                            strtolower(trim($horario->fecha_fin))
                        );
                        $dateEnd = \Carbon\Carbon::parse($dateStrEnd);
                        if (!preg_match('/\d{4}/', $horario->fecha_fin)) {
                            $dateEnd->year($horario->parsed_fecha_inicio->year);
                        }
                        $horario->parsed_fecha_fin = $dateEnd;
                    } else {
                        $horario->parsed_fecha_fin = $horario->parsed_fecha_inicio->copy()->addDays(6);
                    }
                } catch (\Exception $e) {
                    $horario->parsed_fecha_fin = $horario->parsed_fecha_inicio->copy()->addDays(6);
                }

                return $horario;
            });

        $horariosAgrupados = $horarios->groupBy(function($horario) {
            return $horario->parsed_fecha_inicio->format('Y');
        })->map(function($horariosPorAno) {
            return $horariosPorAno->groupBy(function($horario) {
                return $horario->parsed_fecha_inicio->format('m');
            })->sortKeysDesc();
        })->sortKeysDesc();

        $transferMaxKb = app(\App\Services\ScheduleTransfer::class)->maxUploadKb();
        return view('admin.historial-horarios', compact('horarios', 'horariosAgrupados', 'transferMaxKb'));
    }

    public function eliminarHistorial(Request $request, $id)
    {
        $input = $request->validate(['semana_id' => 'required|string', 'version' => 'required|integer|min:1']);
        app(ScheduleDrafts::class)->delete((int) $id, $input['semana_id'], $input['version']);
        return redirect()->route('admin.horarios.historial')->with('success', 'Borrador eliminado. La publicación se conserva.');
    }

    public function generarPlantillaIA(Request $request)
    {
        $request->validate([
            'semana' => ['required', 'string', 'regex:/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/'],
            'horario' => 'required|array:lunes,martes,miercoles,jueves,viernes,sabado,domingo|size:7',
            'horario.*' => 'present|array|max:2',
            'horario.*.*.inicio' => 'required|date_format:H:i',
            'horario.*.*.fin' => 'required|date_format:H:i',
        ]);

        $monday = now()->setISODate((int) substr($request->semana, 0, 4), (int) substr($request->semana, 6))->startOfWeek();
        if ($monday->format('o-\WW') !== $request->semana) {
            throw ValidationException::withMessages(['semana' => 'Selecciona una semana ISO válida.']);
        }

        set_time_limit(150);
        try {
            // Usar la ruta de python definida en el entorno, o intentar predecirla según el SO
            $localPython = base_path('../.local/venv/Scripts/python.exe');
            $defaultPython = PHP_OS_FAMILY === 'Windows'
                ? (is_file($localPython) ? $localPython : 'python')
                : base_path('../algoritmo/venv/bin/python');
            $pythonPath = env('PYTHON_PATH', $defaultPython);
            $scriptPath = base_path('../algoritmo/generar_plantilla_ml.py');
            
            $empleados = User::with(\App\Services\AbsenceAvailability::RELATIONS)
                ->where('activo', true)->where('en_baja', false)
                ->whereIn('rol', ['encargado', 'repartidor', 'auxiliar', 'limpiadora'])
                ->orderBy('id')->get()->map(fn ($emp) => [
                    'id' => $emp->id, 'nombre' => trim($emp->nombre.' '.$emp->apellidos), 'rol' => $emp->rol,
                    'es_menor' => (bool) $emp->es_menor,
                    'horas_mensuales' => $emp->horas_mensuales, 'tipo_disponibilidad' => $emp->tipo_disponibilidad ?? 'completa',
                    'disponibilidad_festivos' => $emp->disponibilidad_festivos ?? 'semanal',
                    'dias_bloqueados_recurrentes' => $emp->dias_bloqueados_recurrentes ?? [],
                    'dias_disponibles' => $emp->dias_disponibles ?? [],
                    'fechas_libres_solicitadas' => $emp->fechas_libres_solicitadas,
                    'vacaciones' => $emp->vacaciones, 'fecha_inicio' => $emp->fecha_inicio?->format('Y-m-d'),
                ])->all();
            // stdin evita límites de longitud de comandos y transmite las restricciones por persona.
            $process = new Process([$pythonPath, '-B', $scriptPath], timeout: 120);
            $process->setInput(json_encode([
                'semana' => $request->semana, 'horario' => $request->horario, 'empleados' => $empleados,
                'referencias_recientes' => app(\App\Services\AutoTemplateHistory::class)->referencias($monday),
                'festivos' => app(\App\Services\HolidayCalendar::class)->dates(),
            ], JSON_THROW_ON_ERROR));
            $process->run();
            $output = $process->getOutput();
            $decoded = json_decode($output, true);

            if (is_array($decoded) && isset($decoded['error'])) {
                return response()->json(['success' => false, 'message' => $decoded['error']], 422);
            }
            
            if ($process->isSuccessful() && $output) {
                if (is_array($decoded) && isset($decoded['plantilla'], $decoded['resumen'], $decoded['avisos'])) {
                    return response()->json(['success' => true] + $decoded);
                }
            }
            
            $output = trim($process->getErrorOutput().$output);
            Log::error("Error al generar plantilla IA. Output: " . $output);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo calcular la plantilla. Revisa las franjas y vuelve a intentarlo.',
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Excepción al generar plantilla IA: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al generar la plantilla.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEventosLocales(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio',
        ]);

        return response()->json([
            'success' => true,
            'html' => '<ul><li><strong>Ejemplo ficticio:</strong> jornada cultural del barrio el viernes; revisa la cobertura de tarde.</li><li><strong>Ejemplo ficticio:</strong> encuentro deportivo el sábado; prueba a reforzar el reparto.</li></ul>',
            'demo' => true,
        ]);
    }
}
