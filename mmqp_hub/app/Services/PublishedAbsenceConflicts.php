<?php

namespace App\Services;

use App\Models\HorarioSemanal;
use App\Models\User;
use Carbon\CarbonImmutable;

class PublishedAbsenceConflicts
{
    private const DAYS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    public function forUser(User $user): array
    {
        $dates = app(AbsenceAvailability::class)->blockedDates($user);
        $conflicts = [];
        if (! $dates) {
            return [];
        }
        foreach (HorarioSemanal::vigentes()->get() as $schedule) {
            if (empty($schedule->roles_publicados)) {
                continue;
            }
            $role = collect($schedule->empleados_snapshot)->firstWhere('id', $user->id)['rol'] ?? $user->rol;
            if (! in_array(strtolower($role), $schedule->roles_publicados, true)) {
                continue;
            }
            foreach ($this->shifts($schedule) as $shift) {
                if ((string) ($shift['empleado_id'] ?? '') !== (string) $user->id) {
                    continue;
                }
                $date = $this->shiftDate($schedule, $shift);
                if ($date && in_array($date, $dates, true)) {
                    $conflicts[] = ['horario_id' => $schedule->id, 'fecha' => $date, 'user_id' => $user->id];
                } elseif (! $date) {
                    // A published assignment without a reconstructible date needs explicit review.
                    $conflicts[] = ['horario_id' => $schedule->id, 'fecha' => 'Fecha sin determinar', 'user_id' => $user->id];
                }
            }
        }

        return collect($conflicts)->unique(fn ($row) => $row['horario_id'].'/'.$row['fecha'])->values()->all();
    }

    public function forSnapshot(array $snapshot): array
    {
        return User::with(AbsenceAvailability::RELATIONS)->whereIn('id', collect($snapshot)->pluck('id')
            ->filter(fn ($id) => ctype_digit((string) $id)))->get()
            ->flatMap(fn ($user) => $this->forUser($user))->all();
    }

    private function shifts(HorarioSemanal $schedule): array
    {
        $shifts = [];
        foreach ($schedule->resultados ?? [] as $row) {
            if (isset($row['turnos_requeridos'])) {
                foreach ($row['turnos_requeridos'] as $shift) {
                    $shifts[] = $shift + ['dia' => $row['dia'] ?? null, 'fecha' => $row['fecha'] ?? null];
                }
            } else {
                $shifts[] = $row;
            }
        }

        return $shifts;
    }

    private function shiftDate(HorarioSemanal $schedule, array $shift): ?string
    {
        if (AbsenceAvailability::validDate($shift['fecha'] ?? null)) {
            return $shift['fecha'];
        }
        $templateDay = collect($schedule->plantilla_diseno)->first(fn ($day) => collect($day['turnos_requeridos'] ?? [])->contains('turno_id', $shift['turno_id'] ?? null));
        if (AbsenceAvailability::validDate($templateDay['fecha'] ?? null)) {
            return $templateDay['fecha'];
        }
        $day = strtolower(strtr($shift['dia'] ?? $templateDay['dia'] ?? '', ['é' => 'e', 'á' => 'a']));
        $offset = array_search($day, self::DAYS, true);
        if ($offset !== false && preg_match('/^(\d{4})-W(\d{2})$/', $schedule->semana_id, $match)) {
            return CarbonImmutable::now(config('app.timezone'))->setISODate((int) $match[1], (int) $match[2], 1)
                ->addDays($offset)->format('Y-m-d');
        }

        return null;
    }
}
