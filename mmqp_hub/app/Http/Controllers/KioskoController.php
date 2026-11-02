<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Fichaje;
use App\Models\HorarioSemanal;
use Carbon\Carbon;
use Illuminate\Support\Str;

class KioskoController extends Controller
{
    /**
     * Devuelve el estado actual: empleados que trabajan hoy o están activos, y los próximos turnos.
     */
    public function status()
    {
        $ahora = Carbon::now();
        $semana_id = clone $ahora;
        $semana_id_str = $semana_id->format('o-\WW');
        $horario = HorarioSemanal::vigentes()->where('semana_id', $semana_id_str)->latest('id')->first();

        // 1. Encontrar IDs de empleados programados para hoy
        $empleadosProgramadosHoy = [];
        $diaNombres = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $nombreDia = $diaNombres[$ahora->dayOfWeek]; // 0 is Sunday
        
        if ($horario && !empty($horario->plantilla_diseno) && !empty($horario->resultados)) {
            $turnosHoyIds = [];
            foreach ($horario->plantilla_diseno as $diaPlan) {
                $diaLimpio = strtolower(Str::ascii($diaPlan['dia']));
                if ($diaLimpio === $nombreDia || $diaLimpio === str_replace('é', 'e', $nombreDia)) {
                    foreach ($diaPlan['turnos_requeridos'] ?? [] as $tr) {
                        $turnosHoyIds[] = $tr['turno_id'];
                    }
                    break;
                }
            }

            foreach ($horario->resultados as $r) {
                if (isset($r['empleado_id']) && in_array($r['turno_id'], $turnosHoyIds)) {
                    $empleadosProgramadosHoy[] = $r['empleado_id'];
                }
            }
        }

        // 2. Fichajes activos (los que tienen entrada pero no salida)
        $fichajesActivos = Fichaje::whereNull('salida')->pluck('entrada', 'user_id');

        // 3. Obtener TODOS los empleados activos (sin filtrar por los que trabajan hoy)
        $empleados = User::employees()->where('activo', true)
            ->select('id', 'nombre', 'apellidos', 'rol')
            ->orderBy('nombre')
            ->get();

        // Formatear empleados con estado actual
        $empleadosStatus = $empleados->map(function ($emp) use ($fichajesActivos) {
            $isWorking = $fichajesActivos->has($emp->id);
            return [
                'id' => $emp->id,
                'nombre' => $emp->nombre,
                'apellidos' => $emp->apellidos,
                'is_working' => $isWorking,
                'entrada_actual' => $isWorking ? $fichajesActivos[$emp->id]->toIso8601String() : null
            ];
        });

        // 4. Obtener próximos turnos (en los siguientes 30 minutos)
        $proximosTurnos = $this->getProximosTurnos($horario, $nombreDia, $ahora, $empleados->modelKeys());
        // A night shift belongs to the day it starts, including at an ISO week boundary.
        $ayer = $ahora->copy()->subDay();
        $horarioAyer = $ayer->format('o-\WW') === $semana_id_str ? $horario
            : HorarioSemanal::vigentes()->where('semana_id', $ayer->format('o-\WW'))->latest('id')->first();
        $proximosTurnos = array_merge($proximosTurnos, $this->getProximosTurnos(
            $horarioAyer, $diaNombres[$ayer->dayOfWeek], $ahora, $empleados->modelKeys(), $ayer,
        ));
        $finVentana = $ahora->copy()->addMinutes(30);
        if (!$ahora->isSameDay($finVentana)) {
            $horarioManana = $finVentana->format('o-\WW') === $semana_id_str ? $horario
                : HorarioSemanal::vigentes()->where('semana_id', $finVentana->format('o-\WW'))->latest('id')->first();
            $proximosTurnos = array_merge($proximosTurnos, $this->getProximosTurnos(
                $horarioManana, $diaNombres[$finVentana->dayOfWeek], $ahora, $empleados->modelKeys(), $finVentana,
            ));
        }

        return response()->json([
            'server_time' => Carbon::now()->toIso8601String(),
            'empleados' => $empleadosStatus,
            'proximos_turnos' => $proximosTurnos
        ]);
    }

    /**
     * Procesa el intento de fichar (entrada o salida).
     */
    public function fichar(Request $request)
    {
        $request->validate([
            'user_id' => ['required', \Illuminate\Validation\Rule::exists('users', 'id')->whereNot('rol', 'admin')],
            'pin'     => 'required|string'
        ]);

        $user = User::employees()->find($request->user_id);

        if (!$user || !$user->activo || empty($user->dni)) {
            return response()->json(['error' => 'Empleado inactivo o sin DNI configurado.'], 401);
        }

        // Extraer solo los números del DNI
        $dniNumeros = preg_replace('/[^0-9]/', '', $user->dni);

        if ($dniNumeros !== $request->pin) {
            return response()->json(['error' => 'PIN incorrecto.'], 401);
        }

        // Buscar si tiene un turno abierto (sin salida)
        $fichajeActivo = Fichaje::where('user_id', $user->id)
                                ->whereNull('salida')
                                ->first();

        $ahora = Carbon::now();

        if ($fichajeActivo) {
            // Registrar salida
            $fichajeActivo->salida = $ahora;
            $fichajeActivo->horas_calculadas = $fichajeActivo->entrada->diffInMinutes($ahora) / 60;
            $fichajeActivo->save();

            return response()->json([
                'success' => true,
                'action'  => 'salida',
                'message' => 'Salida registrada correctamente.',
                'horas'   => round($fichajeActivo->horas_calculadas, 2)
            ]);
        } else {
            // Registrar entrada
            $nuevoFichaje = new Fichaje();
            $nuevoFichaje->user_id = $user->id;
            $nuevoFichaje->entrada = $ahora;
            $nuevoFichaje->origen = 'tablet_kiosko';
            $nuevoFichaje->save();

            return response()->json([
                'success' => true,
                'action'  => 'entrada',
                'message' => 'Entrada registrada correctamente.'
            ]);
        }
    }

    /**
     * Calcula quién entra o sale en los próximos 30 minutos según el HorarioSemanal actual.
     */
    private function getProximosTurnos($horario, $nombreDia, $ahora, array $employeeIds, $fechaTurno = null)
    {
        $dentroDe30Min = $ahora->copy()->addMinutes(30);
        
        if (!$horario || empty($horario->plantilla_diseno) || empty($horario->resultados)) {
            return [];
        }

        // Buscar el diseño del día de hoy
        $turnosHoy = [];
        foreach ($horario->plantilla_diseno as $dia) {
            $diaLimpio = strtolower(Str::ascii($dia['dia']));
            if ($diaLimpio === $nombreDia || $diaLimpio === str_replace('é', 'e', $nombreDia)) {
                if (isset($dia['turnos_requeridos'])) {
                    $turnosHoy = $dia['turnos_requeridos'];
                }
                break;
            }
        }

        if (empty($turnosHoy)) {
            return [];
        }

        $proximos = [];
        
        // Mapear los nombres de empleados
        $empleadosSnap = collect($horario->empleados_snapshot ?? [])->keyBy('id');

        // Buscar en los resultados quién está asignado hoy
        foreach ($horario->resultados as $r) {
            if (!in_array($r['empleado_id'] ?? null, $employeeIds)) {
                continue;
            }
            $turnoId = $r['turno_id'];
            
            // Buscar la info del turno
            $turnoInfo = collect($turnosHoy)->firstWhere('turno_id', $turnoId);
            if ($turnoInfo) {
                $inicioString = $turnoInfo['hora_inicio'] ?? '00:00';
                $finString = $turnoInfo['hora_fin'] ?? '00:00';
                
                $inicio = Carbon::createFromFormat('H:i', $inicioString);
                $fin = Carbon::createFromFormat('H:i', $finString);
                
                // Ajustar fechas reales para poder comparar
                $inicioReal = ($fechaTurno ?? $ahora)->copy()->setTime($inicio->hour, $inicio->minute, 0);
                $finReal = ($fechaTurno ?? $ahora)->copy()->setTime($fin->hour, $fin->minute, 0);
                if ($finReal->lessThan($inicioReal)) {
                    $finReal->addDay(); // Turnos de noche
                }

                $nombre = $r['nombre'] ?? 'Desconocido';
                if (isset($r['empleado_id']) && $empleadosSnap->has($r['empleado_id'])) {
                    $nombre = $empleadosSnap[$r['empleado_id']]['nombre'];
                }

                // ¿Entra pronto? (entre ahora y dentro de 30 mins)
                if ($inicioReal->between($ahora, $dentroDe30Min)) {
                    $proximos[] = [
                        'tipo' => 'entra',
                        'nombre' => $nombre,
                        'hora' => $inicioString
                    ];
                }
                
                // ¿Sale pronto? (entre ahora y dentro de 30 mins)
                if ($finReal->between($ahora, $dentroDe30Min)) {
                    $proximos[] = [
                        'tipo' => 'sale',
                        'nombre' => $nombre,
                        'hora' => $finString
                    ];
                }
            }
        }

        return $proximos;
    }
}
