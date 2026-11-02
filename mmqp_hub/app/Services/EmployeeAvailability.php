<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class EmployeeAvailability
{
    private const DAYS = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];

    /** Query a dated shift, keeping absences separate from weekly/holiday preferences. */
    public function isAvailable(User $employee, string $date, string $period = 'mañana'): bool
    {
        if (! AbsenceAvailability::validDate($date) || ! in_array($period, ['mañana', 'tarde'], true)) {
            return false;
        }
        if (! $employee->activo || $employee->en_baja
            || in_array($date, app(AbsenceAvailability::class)->blockedDates($employee), true)) {
            return false;
        }

        if (app(HolidayCalendar::class)->isHoliday($date)) {
            if ($employee->disponibilidad_festivos === 'no_disponible') {
                return false;
            }
            if ($employee->disponibilidad_festivos === 'disponible') {
                return true;
            }
        }

        $day = self::DAYS[CarbonImmutable::parse($date)->dayOfWeek];
        if (in_array($day, $employee->dias_bloqueados_recurrentes ?? [], true)) {
            return false;
        }
        if (in_array($employee->tipo_disponibilidad, ['personalizada', 'dias_especificos'], true)) {
            $days = $employee->dias_disponibles ?? [];

            return in_array($day, $days, true) || in_array($period, $days[$day] ?? [], true);
        }

        return true;
    }
}
