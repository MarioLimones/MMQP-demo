<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HolidayAssignments
{
    /** Validate against current database settings, never the client's employee snapshot or holiday flags. */
    public function validate(array $layout, array $results, string $week): void
    {
        $holidays = app(HolidayCalendar::class)->dates();
        if ($holidays === []) {
            return;
        }
        $monday = CarbonImmutable::now()->setISODate((int) substr($week, 0, 4), (int) substr($week, 6))->startOfWeek();
        $days = array_flip(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo']);
        $byShift = collect($results)->keyBy('turno_id');
        $assignedIds = collect($layout)->flatMap(fn ($day) => $day['turnos_requeridos'] ?? [])
            ->pluck('empleado_id')->merge(collect($results)->pluck('empleado_id'))->filter()->unique();
        $users = User::with(AbsenceAvailability::RELATIONS)->whereIn('id', $assignedIds)->get()->keyBy('id');
        foreach ($layout as $day) {
            $dayName = Str::ascii(Str::lower(trim($day['dia'] ?? '')));
            if (! array_key_exists($dayName, $days)) {
                throw ValidationException::withMessages(['plantilla_diseno' => 'Cada turno debe pertenecer a un día válido de la semana.']);
            }
            $date = $monday->addDays($days[$dayName])->format('Y-m-d');
            if (! in_array($date, $holidays, true)) {
                continue;
            }
            foreach ($day['turnos_requeridos'] ?? [] as $shift) {
                $id = ($byShift->get($shift['turno_id'] ?? '')['empleado_id'] ?? null) ?? ($shift['empleado_id'] ?? null);
                if (! $id || ! ($user = $users->get($id))) {
                    continue;
                }
                if ($user->disponibilidad_festivos === 'no_disponible') {
                    throw ValidationException::withMessages(['plantilla_diseno' => trim($user->nombre.' '.$user->apellidos).' no está disponible en el festivo '.$date.'.']);
                }
                if ($user->disponibilidad_festivos === 'disponible'
                    && ($user->en_baja || in_array($date, app(AbsenceAvailability::class)->blockedDates($user), true))) {
                    throw ValidationException::withMessages(['plantilla_diseno' => 'La disponibilidad en festivos no permite cancelar una ausencia o baja vigente.']);
                }
            }
        }
    }
}
