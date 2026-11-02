<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\HorarioSemanal;
use App\Notifications\UpcomingShiftNotification;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NotifyUpcomingShifts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'turnos:recordar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía notificaciones push a los empleados con turnos mañana';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $manana = Carbon::tomorrow();
        
        $this->info("Buscando turnos para mañana: {$manana->format('Y-m-d')}");

        $semanaId = $manana->format('o-\WW');
        $horario = HorarioSemanal::vigentes()->where('semana_id', $semanaId)->latest('id')->first();

        if (!$horario) {
            $this->info("No hay horarios configurados que cubran el día de mañana.");
            return;
        }

        $resultados = $horario->resultados ?? [];
        $plantilla = $horario->plantilla_diseno ?? [];
        $year = substr($horario->semana_id, 0, 4);
        $week = substr($horario->semana_id, 6);
        $fecha_inicio_carbon = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $dias_map = ['lunes' => 0, 'martes' => 1, 'miercoles' => 2, 'jueves' => 3, 'viernes' => 4, 'sabado' => 5, 'domingo' => 6];

        $turnos_manana = [];
        foreach ($plantilla as $dia) {
            if (isset($dia['turnos_requeridos'])) {
                $diaNombre = Str::ascii(Str::lower(trim($dia['dia'])));
                if (!isset($dias_map[$diaNombre])) {
                    continue;
                }
                $offset = $dias_map[$diaNombre];
                $fechaExacta = $fecha_inicio_carbon->copy()->addDays($offset);
                
                if ($fechaExacta->format('Y-m-d') === $manana->format('Y-m-d')) {
                    foreach ($dia['turnos_requeridos'] as $turno) {
                        $turnos_manana[$turno['turno_id']] = $turno['hora_inicio'];
                    }
                }
            }
        }

        if (empty($turnos_manana)) {
            $this->info("La plantilla no tiene turnos configurados para mañana.");
            return;
        }

        $usuarios_notificados = [];
        foreach ($resultados as $r) {
            if (isset($r['empleado_id']) && isset($turnos_manana[$r['turno_id']])) {
                $empleado_id = $r['empleado_id'];
                $hora_inicio = $turnos_manana[$r['turno_id']];
                
                if (!isset($usuarios_notificados[$empleado_id])) {
                    $usuarios_notificados[$empleado_id] = [];
                }
                $usuarios_notificados[$empleado_id][] = $hora_inicio;
            }
        }

        // Obtener usuarios activos que tienen turnos
        $usuarios = User::employees()->whereIn('id', array_keys($usuarios_notificados))
            ->where('activo', true)->where('en_baja', false)->get();
        $notificados = 0;

        foreach ($usuarios as $usuario) {
            if (!$usuario->es_admin && !in_array(strtolower($usuario->rol), $horario->roles_publicados ?? [], true)) {
                continue;
            }
            if ($usuario->pushSubscriptions()->count() > 0) {
                $horas = $usuarios_notificados[$usuario->id];
                sort($horas);
                $horaStr = implode(' y a las ', $horas); 
                
                $usuario->notify(new UpcomingShiftNotification($manana->format('d/m/Y'), $horaStr));
                $this->info("Notificación enviada a {$usuario->nombre} para el turno de las {$horaStr}");
                $notificados++;
            }
        }

        $this->info("Proceso de notificaciones completado. Notificados: {$notificados}");
    }
}
