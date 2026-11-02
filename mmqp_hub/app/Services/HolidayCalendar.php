<?php

namespace App\Services;

use App\Models\Festivo;

class HolidayCalendar
{
    /** Actual configured dates; weekends and AI event suggestions are never inferred as holidays. */
    public function dates(): array
    {
        return Festivo::query()->orderBy('fecha')->get(['fecha'])
            ->map(fn (Festivo $holiday) => $holiday->fecha->format('Y-m-d'))->all();
    }

    public function isHoliday(string $date): bool
    {
        return AbsenceAvailability::validDate($date) && Festivo::query()->whereDate('fecha', $date)->exists();
    }
}
