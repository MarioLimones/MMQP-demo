<?php

namespace App\Services;

use App\Models\HorarioSemanal;
use Carbon\Carbon;

class AutoTemplateHistory
{
    private const ROLES = ['encargado', 'repartidor', 'auxiliar', 'limpiadora', 'oficina'];

    /**
     * Return staffing demand from the immediately preceding published week.
     * Empty published roles are explicit zero demand, rather than missing history.
     */
    public function referencias(Carbon $monday): array
    {
        $from = $monday->copy()->startOfDay()->subWeek()->toDateString();
        $until = $monday->copy()->startOfDay()->subDay()->toDateString();
        $references = [];

        $schedules = HorarioSemanal::vigentes()->where('estado', 'published')
            ->whereDate('fecha_inicio', '<=', $until)->whereDate('fecha_fin', '>=', $from)
            ->orderBy('id')->get(['id', 'plantilla_diseno', 'roles_publicados']);

        foreach ($schedules as $schedule) {
            $roles = array_values(array_intersect(self::ROLES, $schedule->roles_publicados ?? []));
            if (! $roles) {
                continue;
            }
            foreach ($schedule->plantilla_diseno ?? [] as $day) {
                $date = $day['fecha'] ?? null;
                if (! AbsenceAvailability::validDate($date) || $date < $from || $date > $until) {
                    continue;
                }

                // The latest active publication replaces each published role in full.
                foreach ($roles as $role) {
                    $references[$date][$role] = [];
                }
                foreach ($day['turnos_requeridos'] ?? [] as $shift) {
                    $role = $shift['rol_necesario'] ?? null;
                    $start = $shift['hora_inicio'] ?? null;
                    $end = $shift['hora_fin'] ?? null;
                    if (! in_array($role, $roles, true) || ! $this->validTime($start) || ! $this->validTime($end) || $start === $end) {
                        continue;
                    }
                    // Keep repeated shifts: each one represents a required person.
                    $references[$date][$role][] = ['hora_inicio' => $start, 'hora_fin' => $end];
                }
            }
        }

        ksort($references);

        return $references;
    }

    private function validTime(mixed $time): bool
    {
        return is_string($time) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time) === 1;
    }
}
