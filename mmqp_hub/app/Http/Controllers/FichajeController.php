<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fichaje;
use App\Models\User;
use App\Models\HorarioSemanal;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FichajeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['fecha' => 'sometimes|date_format:Y-m-d']);
        $fechaStr = $request->input('fecha', Carbon::now(Fichaje::TIMEZONE)->format('Y-m-d'));
        $fecha = Carbon::parse($fechaStr);

        // 1. Obtener horario semanal activo para esta fecha usando semana_id
        $semana_id_str = $fecha->format('o-\WW');
        
        $horario = HorarioSemanal::vigentes()->where('semana_id', $semana_id_str)->latest('id')->first();

        $turnosTeoricos = []; // user_id => [ ['inicio' => '12:00', 'fin' => '16:00'], ... ]
        $diaNombres = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $nombreDia = $diaNombres[$fecha->dayOfWeek]; // 0 is Sunday
        
        // Mapeo temporal si el JSON tiene acentos (Miércoles -> Miercoles)
        $nombreDiaBuscado = $nombreDia == 'Miércoles' ? 'Miercoles' : $nombreDia;
        $nombreDiaBuscado2 = $nombreDia == 'Sábado' ? 'Sabado' : $nombreDiaBuscado;

        if ($horario && $horario->plantilla_diseno) {
            $turnosRequeridosHoy = [];
            foreach ($horario->plantilla_diseno as $diaPlan) {
                // Buscamos el día ignorando acentos
                if (strtolower(Str::ascii($diaPlan['dia'] ?? '')) === strtolower(Str::ascii($nombreDia))) {
                    foreach ($diaPlan['turnos_requeridos'] ?? [] as $tr) {
                        $turnosRequeridosHoy[$tr['turno_id']] = [
                            'inicio' => $tr['hora_inicio'],
                            'fin' => $tr['hora_fin']
                        ];
                    }
                    break;
                }
            }

            if ($horario->resultados) {
                foreach ($horario->resultados as $res) {
                    if (isset($res['empleado_id']) && isset($turnosRequeridosHoy[$res['turno_id']])) {
                        $empId = $res['empleado_id'];
                        if (!isset($turnosTeoricos[$empId])) {
                            $turnosTeoricos[$empId] = [];
                        }
                        $turnosTeoricos[$empId][] = $turnosRequeridosHoy[$res['turno_id']];
                    }
                }
            }
        }

        // 2. Obtener fichajes reales de hoy
        $fichajesReales = Fichaje::betweenLocalDates($fechaStr, $fechaStr)->orderBy('entrada', 'asc')->get();
        $fichajesPorUser = [];
        foreach ($fichajesReales as $f) {
            if (!isset($fichajesPorUser[$f->user_id])) {
                $fichajesPorUser[$f->user_id] = [];
            }
            $fichajesPorUser[$f->user_id][] = $f;
        }

        // 3. Obtener empleados activos y dividirlos
        $todosEmpleados = User::employees()->where('activo', true)->orderBy('nombre')->get();
        
        $trabajanHoy = collect();
        $noTrabajanHoy = collect();

        foreach ($todosEmpleados as $emp) {
            $teoricos = $turnosTeoricos[$emp->id] ?? [];
            $reales = $fichajesPorUser[$emp->id] ?? [];
            
            // Adjuntamos la data al modelo para usarla en la vista
            $emp->turnos_teoricos = $teoricos;
            $emp->fichajes_reales = $reales;

            if (count($teoricos) > 0 || count($reales) > 0) {
                $trabajanHoy->push($emp);
            } else {
                $noTrabajanHoy->push($emp);
            }
        }

        // Group by rol for the view
        $trabajanHoy = collect($trabajanHoy)->groupBy('rol');
        $noTrabajanHoy = collect($noTrabajanHoy)->groupBy('rol');

        $empleadosDict = [];
        foreach ($todosEmpleados as $emp) {
            $empleadosDict[$emp->id] = [
                'id' => $emp->id,
                'nombre' => $emp->nombre,
                'apellidos' => $emp->apellidos,
                'rol' => $emp->rol,
                'turnos_teoricos' => $emp->turnos_teoricos,
                'fichajes_reales' => $emp->fichajes_reales
            ];
        }

        return view('admin.fichajes', compact('fechaStr', 'trabajanHoy', 'noTrabajanHoy', 'empleadosDict'));
    }

    public function asignarTurnoExtra(Request $request, $user_id)
    {
        $input = $request->validate([
            'fecha' => 'required|date_format:Y-m-d', 'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'draft_id' => 'required|integer', 'version' => 'required|integer|min:1', 'request_id' => 'required|uuid',
        ]);
        $fecha = Carbon::parse($input['fecha']);
        $week = $fecha->format('o-\WW');
        $drafts = app(\App\Services\ScheduleDrafts::class);
        $draft = $drafts->requireDraft($input['draft_id'], $week, $input['version']);
        $user = User::employees()->findOrFail($user_id);
        $dayName = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'][$fecha->dayOfWeek];
        $layout = $draft->plantilla_diseno;
        $results = $draft->resultados;
        $shiftId = 'EXTRA-'.$input['request_id'];
        $shift = ['turno_id' => $shiftId, '_idLocal' => $shiftId, 'rol_necesario' => strtolower($user->rol),
            'hora_inicio' => $input['hora_inicio'], 'hora_fin' => $input['hora_fin'],
            'duracion_horas' => Carbon::parse($input['hora_inicio'])->diffInMinutes(Carbon::parse($input['hora_fin'])) / 60];
        $found = false;
        foreach ($layout as &$day) {
            if (strtolower(Str::ascii($day['dia'] ?? '')) === $dayName) {
                $day['turnos_requeridos'][] = $shift;
                $found = true;
                break;
            }
        }
        unset($day);
        if (!$found) {
            $layout[] = ['dia' => $dayName, 'fecha' => $input['fecha'], 'turnos_requeridos' => [$shift]];
        }
        $results[] = ['turno_id' => $shiftId, 'empleado_id' => $user->id, 'nombre' => $user->nombre,
            'hora_inicio' => $input['hora_inicio'], 'hora_fin' => $input['hora_fin'], 'duracion_horas' => $shift['duracion_horas']];
        $employees = $draft->empleados_snapshot;
        if (!collect($employees)->contains('id', $user->id)) {
            $employees[] = ['id' => $user->id, 'nombre' => $user->nombre, 'rol' => strtolower($user->rol)];
        }
        $saved = $drafts->save($draft->id, $week, $input['version'], $input['request_id'], [
            'fecha_inicio' => $draft->fecha_inicio, 'fecha_fin' => $draft->fecha_fin,
            'plantilla_diseno' => $layout, 'resultados' => $results, 'empleados_snapshot' => $employees,
        ]);
        $url = route('admin.horarios.resultado', $saved->id);
        return $request->expectsJson() ? response()->json(['success' => true, 'redirect_url' => $url])
            : redirect($url)->with('success', 'Turno extra guardado en el borrador. Publícalo explícitamente para hacerlo visible.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->whereNot('rol', 'admin')],
            'fecha' => 'required|date_format:Y-m-d',
            'entrada' => 'required|date_format:H:i',
            'salida' => 'nullable|date_format:H:i',
            'notas' => 'nullable|string'
        ]);

        $fechaBase = Carbon::parse($request->fecha)->format('Y-m-d');
        
        $fichaje = new Fichaje();
        $fichaje->user_id = $request->user_id;
        $fichaje->entrada = Carbon::parse($fechaBase . ' ' . $request->entrada);
        
        if ($request->salida) {
            $salida = Carbon::parse($fechaBase . ' ' . $request->salida);
            // Si la salida es a una hora menor que la entrada (ej: entra 20:00, sale 01:00), es al día siguiente
            if ($salida->lt($fichaje->entrada)) {
                $salida->addDay();
            }
            $fichaje->salida = $salida;
            $fichaje->horas_calculadas = $fichaje->entrada->diffInMinutes($fichaje->salida) / 60;
        }

        $fichaje->origen = 'manual_admin';
        $fichaje->notas = $request->notas;
        $fichaje->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'fichaje' => $fichaje]);
        }
        return back()->with('success', 'Fichaje creado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'entrada' => 'required|date_format:H:i',
            'salida' => 'nullable|date_format:H:i',
            'notas' => 'nullable|string'
        ]);

        $fechaBase = Carbon::parse($request->fecha)->format('Y-m-d');
        $fichaje = Fichaje::findOrFail($id);
        
        $zone = $fichaje->usesUtcTimes() ? Fichaje::TIMEZONE : config('app.timezone');
        $fichaje->entrada = Carbon::parse($fechaBase . ' ' . $request->entrada, $zone)->setTimezone(config('app.timezone'));
        
        if ($request->salida) {
            $salida = Carbon::parse($fechaBase . ' ' . $request->salida, $zone)->setTimezone(config('app.timezone'));
            if ($salida->lt($fichaje->entrada)) {
                $salida->addDay();
            }
            $fichaje->salida = $salida;
            $fichaje->horas_calculadas = $fichaje->entrada->diffInMinutes($fichaje->salida) / 60;
        } else {
            $fichaje->salida = null;
            $fichaje->horas_calculadas = null;
        }

        $fichaje->notas = $request->notas;
        $fichaje->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'fichaje' => $fichaje]);
        }
        return back()->with('success', 'Fichaje actualizado correctamente.');
    }

    public function destroy($id)
    {
        $fichaje = Fichaje::findOrFail($id);
        $fichaje->delete();

        return back()->with('success', 'Fichaje eliminado correctamente.');
    }
}
