<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HorarioSemanal;
use Carbon\Carbon;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        $now = Carbon::now();
        $query = HorarioSemanal::vigentes()->where('semana_id', $now->format('o-\WW'))
                                       ->orderBy('id', 'desc');
        if (!$user->es_admin) {
            $query->whereJsonContains('roles_publicados', strtolower($user->rol));
        }
        $ultimoHorario = $query->first();
        
        // Si no hay horario esta semana, buscar el más próximo en el futuro
        if (!$ultimoHorario) {
            $futuroQuery = HorarioSemanal::vigentes()->where('semana_id', '>', $now->format('o-\WW'))
                                           ->orderBy('semana_id', 'asc')
                                           ->orderBy('id', 'desc');
            if (!$user->es_admin) {
                $futuroQuery->whereJsonContains('roles_publicados', strtolower($user->rol));
            }
            $ultimoHorario = $futuroQuery->first();
        }
        
        $misTurnos = [];
        $turnosAgrupados = [];
        $proximoTurno = null;
        
        if ($ultimoHorario) {
            $resultados = $ultimoHorario->resultados ?? [];
            $plantilla = $ultimoHorario->plantilla_diseno ?? [];
            
            // Gather the turn IDs assigned to this user
            $misTurnoIds = [];
            foreach ($resultados as $r) {
                if (isset($r['empleado_id']) && $r['empleado_id'] == $user->id) {
                    $misTurnoIds[] = $r['turno_id'];
                }
            }
            
            $year = substr($ultimoHorario->semana_id, 0, 4);
            $week = substr($ultimoHorario->semana_id, 6);
            $fecha_inicio_carbon = Carbon::now()->setISODate($year, $week)->startOfWeek();
            $dias_map = [
                'lunes' => 0, 'martes' => 1, 'miercoles' => 2, 'jueves' => 3,
                'viernes' => 4, 'sabado' => 5, 'domingo' => 6
            ];

            // Reconstruct the shifts with actual time details
            foreach ($plantilla as $dia) {
                if (isset($dia['turnos_requeridos'])) {
                    // Compute exact date for this day of the week
                    $diaNombre = strtolower(trim($dia['dia']));
                    $offset = $dias_map[$diaNombre] ?? 0;
                    $fechaExacta = $fecha_inicio_carbon->copy()->addDays($offset)->format('Y-m-d');

                    foreach ($dia['turnos_requeridos'] as $turno) {
                        if (in_array($turno['turno_id'], $misTurnoIds)) {
                            $misTurnos[] = [
                                'dia' => $dia['dia'],
                                'fecha' => $fechaExacta, // Robust computed date (Y-m-d)
                                'hora_inicio' => $turno['hora_inicio'],
                                'hora_fin' => $turno['hora_fin'],
                                'rol' => $turno['rol_necesario'],
                                'duracion' => $turno['duracion_horas'],
                            ];
                        }
                    }
                }
            }
            
            $parseDate = function ($fecha, $hora) {
                // Ensure hour has leading zero if needed
                $parts = explode(':', trim($hora));
                $h = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
                $m = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
                $h_str = $h . ':' . $m;
                
                try {
                    if (str_contains($fecha, '/')) {
                        return Carbon::createFromFormat('d/m/Y H:i', trim($fecha) . ' ' . $h_str);
                    }
                    return Carbon::createFromFormat('Y-m-d H:i', trim($fecha) . ' ' . $h_str);
                } catch (\Exception $e) {
                    // Fallback to simple parse if format fails
                    return Carbon::parse(trim($fecha) . ' ' . $h_str);
                }
            };

            usort($misTurnos, function ($a, $b) use ($parseDate) {
                $dateA = $parseDate($a['fecha'], $a['hora_inicio']);
                $dateB = $parseDate($b['fecha'], $b['hora_inicio']);
                return $dateA <=> $dateB;
            });
            
            $now = Carbon::now();
            foreach ($misTurnos as $turno) {
                $inicio = $parseDate($turno['fecha'], $turno['hora_inicio']);
                
                if ($inicio->copy()->addHours(12) >= $now) { 
                    $proximoTurno = $turno;
                    break;
                }
            }
            
            // Si no hay ningún turno futuro en este horario (porque todos son del pasado), 
            // dejamos $proximoTurno como null para que el dashboard muestre "Sin turnos programados"
            // en lugar de mostrar un turno de hace una semana como si fuera el próximo.
            // Group by date
            $turnosAgrupados = [];
            foreach ($misTurnos as $turno) {
                $fecha = $turno['fecha'];
                if (!isset($turnosAgrupados[$fecha])) {
                    $turnosAgrupados[$fecha] = [];
                }
                $turnosAgrupados[$fecha][] = $turno;
            }
        }
        
        return view('dashboard', compact('misTurnos', 'turnosAgrupados', 'proximoTurno', 'ultimoHorario'));
    }

    public function calendario()
    {
        $user = auth()->user();
        
        $query = HorarioSemanal::vigentes();
        if (!$user->es_admin) {
            $query->whereJsonContains('roles_publicados', strtolower($user->rol));
        }
        $horarios = $query->get();
        
        $eventos = [];

        foreach ($horarios as $horario) {
            $resultados = $horario->resultados ?? [];
            $plantilla = $horario->plantilla_diseno ?? [];
            
            $misTurnoIds = [];
            foreach ($resultados as $r) {
                if (isset($r['empleado_id']) && $r['empleado_id'] == $user->id) {
                    $misTurnoIds[] = $r['turno_id'];
                }
            }
            
            if (empty($misTurnoIds)) {
                continue;
            }
            
            $year = substr($horario->semana_id, 0, 4);
            $week = substr($horario->semana_id, 6);
            $fecha_inicio_carbon = Carbon::now()->setISODate($year, $week)->startOfWeek();
            $dias_map = [
                'lunes' => 0, 'martes' => 1, 'miercoles' => 2, 'jueves' => 3,
                'viernes' => 4, 'sabado' => 5, 'domingo' => 6
            ];

            foreach ($plantilla as $dia) {
                if (isset($dia['turnos_requeridos'])) {
                    $diaNombre = strtolower(trim($dia['dia']));
                    $offset = $dias_map[$diaNombre] ?? 0;
                    $fechaExacta = $fecha_inicio_carbon->copy()->addDays($offset)->format('Y-m-d');

                    foreach ($dia['turnos_requeridos'] as $turno) {
                        if (in_array($turno['turno_id'], $misTurnoIds)) {
                            // Crear el evento para FullCalendar
                            // Asegurar formato de horas
                            $h_inicio = str_pad($turno['hora_inicio'], 5, '0', STR_PAD_LEFT);
                            $h_fin = str_pad($turno['hora_fin'], 5, '0', STR_PAD_LEFT);
                            
                            $start_date = $fechaExacta . 'T' . $h_inicio . ':00';
                            
                            // Si la hora de fin es menor a la de inicio, asume que termina el día siguiente
                            $end_date = $fechaExacta . 'T' . $h_fin . ':00';
                            if (str_replace(':', '', $h_fin) < str_replace(':', '', $h_inicio)) {
                                $end_date = Carbon::parse($fechaExacta)->addDay()->format('Y-m-d') . 'T' . $h_fin . ':00';
                            }
                            
                            $eventos[] = [
                                'id' => $turno['turno_id'] . '_' . $fechaExacta,
                                'title' => $h_inicio . ' a ' . $h_fin,
                                'start' => $start_date,
                                // Omitimos 'end' para evitar que se pinte un bloque que abarque dos días
                                'backgroundColor' => '#6366f1', // color indigo-500
                                'borderColor' => '#4f46e5',
                                'textColor' => '#ffffff', // Forzar texto en blanco para visibilidad
                            ];
                        }
                    }
                }
            }
        }
        
        return view('calendario', compact('eventos'));
    }

    public function calendarioGlobal(Request $request)
    {
        $semana = $request->query('semana', Carbon::now()->format('o-\WW'));
        $user = auth()->user();

        $query = HorarioSemanal::vigentes()->where('semana_id', $semana)->orderBy('id', 'desc');
        if (!$user->es_admin) {
            $query->whereJsonContains('roles_publicados', strtolower($user->rol));
        }
        $horario = $query->first();

        // Calcular fechas para la semana solicitada
        $year = substr($semana, 0, 4);
        $week = substr($semana, 6);
        $date = Carbon::now()->locale('es')->setISODate($year, $week);
        $fecha_inicio = ucfirst($date->copy()->startOfWeek()->isoFormat('D MMM'));
        $fecha_fin = ucfirst($date->copy()->endOfWeek()->isoFormat('D MMM YYYY'));

        if ($horario) {
            $data = [
                'resultados' => $horario->resultados,
                'plantilla'  => $horario->plantilla_diseno,
                'metadatos'  => [
                    'semana_id'    => $horario->semana_id,
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin'    => $fecha_fin,
                ],
                'empleados'  => $horario->empleados_snapshot,
                'horario_id' => $horario->id,
                'estado' => $horario->estado,
                'version' => $horario->version,
                'published_draft_version' => $horario->published_draft_version,
                'vigente' => $horario->vigente,
            ];
            $id = $horario->id;
        } else {
            $empleados = User::employees()->where('en_baja', 0)->orderBy('fecha_inicio', 'asc')->orderBy('id', 'asc')->get()->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'nombre' => $emp->nombre,
                    'apellidos' => $emp->apellidos,
                    'rol' => $emp->rol,
                    'horas_mensuales' => $emp->horas_mensuales,
                    'vacaciones' => $emp->vacaciones ?? [],
                    'fechas_libres_solicitadas' => $emp->fechas_libres_solicitadas ?? [],
                    'orden_visual' => $emp->orden_visual ?? 0,
                ];
            })->toArray();

            $plantilla = [];
            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            foreach ($dias as $d) {
                $plantilla[] = ['dia' => $d, 'turnos_requeridos' => []];
            }

            $data = [
                'resultados' => [],
                'plantilla'  => $plantilla,
                'metadatos'  => [
                    'semana_id'    => $semana,
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin'    => $fecha_fin,
                ],
                'empleados'  => $empleados,
                'horario_id' => null,
                'estado' => 'empty',
            ];
            $id = null;
        }

        $es_borrador = false;

        $data['empleados'] = app(\App\Services\AbsenceAvailability::class)->refreshSnapshot($data['empleados'] ?? []);
        return view('admin.horarios-resultado', compact('data', 'id', 'es_borrador', 'semana'));
    }
}
