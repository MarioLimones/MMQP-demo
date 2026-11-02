<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;

class AbsenceAvailability
{
    public const RELATIONS = ['ausenciasVigentes', 'availabilityBlocks', 'legacyAvailabilityImport'];

    public static function validDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    public function legacyDates(User $user): array
    {
        $dates = [];
        foreach (['vacaciones', 'fechas_libres_solicitadas'] as $source) {
            $raw = $user->getRawOriginal($source);
            $values = $this->legacyValues($raw);
            if ($values === null) {
                continue;
            }
            foreach ($values as $date) {
                if (self::validDate($date)) {
                    $dates[] = ['fecha' => $date, 'source' => $source];
                }
            }
        }

        return collect($dates)->unique(fn ($row) => $row['source'].'/'.$row['fecha'])->values()->all();
    }

    public function legacyValues(mixed $raw): ?array
    {
        // Older imports sometimes passed JSON strings through Eloquent's array cast a second time.
        for ($layer = 0; is_string($raw) && $layer < 2; $layer++) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) && array_is_list($raw) ? $raw : null;
    }

    public function historicalDates(User $user): array
    {
        if (! $user->legacyAvailabilityImport) {
            return $this->sorted(array_column($this->legacyDates($user), 'fecha'));
        }

        return $this->sorted($user->availabilityBlocks
            ->whereIn('status', ['pending_review', 'confirmed'])->pluck('fecha')->all());
    }

    public function vacationDates(User $user): array
    {
        return $this->sorted($user->ausenciasVigentes->where('tipo', 'vacaciones')
            ->flatMap(fn ($absence) => $absence->getDatesRange())->all());
    }

    public function unresolvedDates(User $user): array
    {
        return ! $user->legacyAvailabilityImport ? $this->historicalDates($user)
            : $this->sorted($user->availabilityBlocks->where('status', 'pending_review')->pluck('fecha')->all());
    }

    public function blockedDates(User $user): array
    {
        return $this->sorted(array_merge($this->historicalDates($user), $user->ausenciasVigentes
            ->flatMap(fn ($absence) => $absence->getDatesRange())->all()));
    }

    public function sorted(array $dates): array
    {
        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    // Refresh availability for view/cache snapshots, without changing stored schedules or names/roles.
    public function refreshSnapshot(array $snapshot): array
    {
        $users = User::with(self::RELATIONS)->whereIn('id', collect($snapshot)->pluck('id')
            ->filter(fn ($id) => ctype_digit((string) $id)))->get()->keyBy('id');
        foreach ($snapshot as &$employee) {
            if ($user = $users->get($employee['id'] ?? null)) {
                $employee['vacaciones'] = $this->vacationDates($user);
                $employee['fechas_libres_solicitadas'] = $this->blockedDates($user);
                $employee['en_baja'] = $user->en_baja;
                $employee['activo'] = (bool) $user->activo;
                $employee['disponibilidad_festivos'] = $user->disponibilidad_festivos ?? 'semanal';
            } else {
                $employee['activo'] = false;
            }
        }

        return $snapshot;
    }
}
