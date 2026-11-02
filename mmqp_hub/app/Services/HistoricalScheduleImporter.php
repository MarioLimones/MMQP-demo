<?php

namespace App\Services;

use App\Models\HorarioSemanal;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class HistoricalScheduleImporter
{
    private const DAYS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    /** Validate every source record before the transaction can create its first snapshot. */
    public function import(mixed $input, bool $dryRun = false, ?int $generatedBy = null): array
    {
        if (! is_array($input)) {
            throw new InvalidArgumentException('El JSON debe contener una lista de turnos o un objeto con turnos e incidencias.');
        }
        if (array_is_list($input)) {
            $rawRows = array_map(static fn ($row) => ['tipo' => 'turno', 'row' => $row], $input);
        } else {
            if (array_key_exists('errores_extraccion', $input)
                && (! is_int($input['errores_extraccion']) || $input['errores_extraccion'] !== 0)) {
                throw new InvalidArgumentException('El lote declara errores de extracción. Revise las fuentes antes de importar.');
            }
            $rawRows = [];
            foreach (['turnos' => 'turno', 'incidencias' => 'incidencia'] as $key => $type) {
                $values = $input[$key] ?? [];
                if (! is_array($values) || ! array_is_list($values)) {
                    throw new InvalidArgumentException($key.' debe ser una lista.');
                }
                foreach ($values as $row) {
                    $rawRows[] = ['tipo' => $type, 'row' => $row];
                }
            }
        }
        if ($rawRows === []) {
            throw new InvalidArgumentException('El JSON no contiene turnos ni incidencias.');
        }
        $rows = [];
        $ignoredDeletedRows = 0;
        foreach ($rawRows as $index => $raw) {
            if ($raw['tipo'] === 'incidencia' && $this->isDeletedIncident($raw['row'])) {
                $ignoredDeletedRows++;
                continue;
            }
            $row = $this->normalize($raw['row'], $raw['tipo'], $index + 1);
            if (isset($rows[$row['source_id']]) && $rows[$row['source_id']] !== $row) {
                throw new InvalidArgumentException('Fila '.($index + 1).': source_id repetido con datos distintos.');
            }
            $rows[$row['source_id']] = $row;
        }
        $summary = ['input_rows' => count($rawRows), 'ignored_deleted_rows' => $ignoredDeletedRows,
            'unique_rows' => count($rows), 'duplicates_in_input' => count($rawRows) - $ignoredDeletedRows - count($rows),
            'already_imported' => 0, 'new_shifts' => 0, 'new_incidents' => 0, 'new_weeks' => 0,
            'created_ids' => [], 'dry_run' => $dryRun];
        if ($rows === []) {
            return $summary;
        }

        return DB::transaction(function () use ($summary, $rows, $dryRun, $generatedBy) {
            $existing = [];
            foreach (HorarioSemanal::where('estado', 'historico')->lockForUpdate()->get() as $schedule) {
                foreach ($schedule->plantilla_diseno ?? [] as $day) {
                    foreach ($day['turnos_requeridos'] ?? [] as $shift) {
                        if (isset($shift['source_id'])) {
                            $this->remember($existing, $this->storedShift($shift));
                        }
                    }
                    foreach ($day['incidencias_importacion'] ?? [] as $incident) {
                        if ($this->isDeletedIncident($incident)) {
                            continue;
                        }
                        $this->remember($existing, $this->normalize($incident, 'incidencia', 0));
                    }
                }
            }

            $weeks = [];
            $alreadyImported = $newShifts = $newIncidents = 0;
            foreach ($rows as $source => $row) {
                if (isset($existing[$source])) {
                    if ($existing[$source] !== $row) {
                        throw new RuntimeException('Un source_id ya existe con datos distintos. El histórico se conserva sin cambios.');
                    }
                    $alreadyImported++;
                    continue;
                }
                $date = new DateTimeImmutable($row['fecha']);
                $weeks[$date->format('o-\WW')][] = $row;
                $row['tipo'] === 'turno' ? $newShifts++ : $newIncidents++;
            }
            ksort($weeks);
            $createdIds = [];
            foreach ($weeks as $week => $weekRows) {
                $snapshot = $this->snapshot($week, $weekRows, $generatedBy);
                if (! $dryRun) {
                    $createdIds[] = HorarioSemanal::create($snapshot)->id;
                }
            }

            return array_replace($summary, ['already_imported' => $alreadyImported,
                'new_shifts' => $newShifts, 'new_incidents' => $newIncidents, 'new_weeks' => count($weeks),
                'created_ids' => $createdIds]);
        });
    }

    private function isDeletedIncident(mixed $raw): bool
    {
        if (! is_array($raw)) {
            return false;
        }
        $code = is_array($raw['datos_origen'] ?? null) ? ($raw['datos_origen']['codigo'] ?? null) : null;
        $notes = $raw['notas_dia'] ?? null;

        return (is_string($code) && Str::lower(trim($code)) === 'borrado_documento')
            || (is_string($notes) && preg_match('/^Borrado\b/iu', trim($notes)) === 1);
    }

    private function withoutDeletedAnnotations(string $notes): string
    {
        $segments = array_map('trim', explode(';', $notes));

        return implode('; ', array_filter($segments, static fn ($segment) => $segment !== '' && preg_match('/^Borrado\b/iu', $segment) !== 1));
    }

    private function remember(array &$existing, array $row): void
    {
        $source = $row['source_id'];
        if (isset($existing[$source]) && $existing[$source] !== $row) {
            throw new RuntimeException('Hay históricos con un mismo source_id y datos incompatibles.');
        }
        $existing[$source] = $row;
    }

    private function normalize(mixed $raw, string $type, int $line): array
    {
        $fail = static fn (string $message) => throw new InvalidArgumentException('Fila '.$line.': '.$message);
        if (! is_array($raw)) {
            $fail('cada registro debe ser un objeto.');
        }
        foreach (['source_id', 'fecha', 'empleado', 'empleado_documento', 'archivo_origen'] as $field) {
            if (! isset($raw[$field]) || ! is_string($raw[$field]) || trim($raw[$field]) === '') {
                $fail('falta '.$field.'. Se requieren fechas completas y datos de origen; no se deducen de fotografías.');
            }
        }
        $date = trim($raw['fecha']);
        if (! AbsenceAvailability::validDate($date)) {
            $fail('fecha debe ser una fecha real YYYY-MM-DD.');
        }
        $pages = $raw['paginas_origen'] ?? null;
        if (! is_array($pages) || ! array_is_list($pages) || $pages === []) {
            $fail('paginas_origen debe ser una lista no vacía de páginas.');
        }
        foreach ($pages as $page) {
            if (! is_int($page) || $page < 1) {
                $fail('paginas_origen contiene una página inválida.');
            }
        }
        $pages = array_values(array_unique($pages));
        sort($pages);
        $optional = [];
        foreach (['archivo_sha256', 'puesto_documento', 'fichaje_entrada', 'fichaje_salida', 'notas_dia'] as $field) {
            if (isset($raw[$field]) && ! is_string($raw[$field])) {
                $fail($field.' debe ser texto.');
            }
            $optional[$field] = trim($raw[$field] ?? '');
        }
        if ($type === 'turno') {
            $optional['notas_dia'] = $this->withoutDeletedAnnotations($optional['notas_dia']);
        }
        if (isset($raw['datos_origen']) && ! is_array($raw['datos_origen'])) {
            $fail('datos_origen debe ser un objeto o una lista.');
        }
        $row = ['tipo' => $type, 'source_id' => trim($raw['source_id']), 'fecha' => $date,
            'empleado' => trim($raw['empleado']), 'empleado_documento' => trim($raw['empleado_documento']),
            'archivo_origen' => trim($raw['archivo_origen']), 'paginas_origen' => $pages] + $optional;
        $row['datos_origen'] = $this->canonicalData($raw['datos_origen'] ?? []);
        if ($type === 'incidencia') {
            if ($row['notas_dia'] === '') {
                $fail('una incidencia necesita notas_dia que expliquen el dato documental.');
            }
            if (! empty($raw['hora_inicio']) || ! empty($raw['hora_fin'])) {
                $fail('una incidencia no debe contener extremos de un turno incompleto. Consérvelos en datos_origen.');
            }
            $row['puesto'] = isset($raw['puesto']) && is_string($raw['puesto']) && trim($raw['puesto']) !== ''
                ? ($this->role($raw['puesto']) ?? 'sin_puesto') : 'sin_puesto';

            return $row;
        }
        foreach (['hora_inicio', 'hora_fin', 'puesto'] as $field) {
            if (! isset($raw[$field]) || ! is_string($raw[$field]) || trim($raw[$field]) === '') {
                $fail('falta '.$field.'.');
            }
        }
        $start = trim($raw['hora_inicio']);
        $end = trim($raw['hora_fin']);
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start)
            || ! preg_match('/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/D', $end)) {
            $fail('las horas deben ser HH:mm; 24:00 solo se admite como fin.');
        }
        if ($start === $end && ($raw['duracion_minutos'] ?? null) !== 0) {
            $fail('entrada y salida iguales requieren duracion_minutos igual a cero explícito; use 00:00 a 24:00 para un día completo.');
        }
        $role = $this->role($raw['puesto']);
        if ($role === null) {
            $fail('puesto no identifica un rol reconocido; use sin_puesto para un puesto ausente en el documento.');
        }
        $minutes = $this->minutes($end) - $this->minutes($start);
        if ($minutes < 0) {
            $minutes += 1440;
        }
        if (array_key_exists('duracion_minutos', $raw)
            && (! is_int($raw['duracion_minutos']) || $raw['duracion_minutos'] !== $minutes)) {
            $fail('duracion_minutos no coincide con los extremos del intervalo.');
        }

        return $row + ['hora_inicio' => $start, 'hora_fin' => $end, 'puesto' => $role, 'duracion_minutos' => $minutes];
    }

    private function role(string $role): ?string
    {
        $role = Str::lower(Str::ascii(trim($role)));
        if ($role === 'sin_puesto') {
            return $role;
        }
        $matches = [];
        foreach (['encargado' => '/\b(?:encargad[oa]s?|gerentes?)\b/',
            'repartidor' => '/\b(?:repartidor(?:a|es|as)?|moter[oa]s?)\b/',
            'auxiliar' => '/\bauxiliar(?:es)?\b/', 'limpiadora' => '/\b(?:limpiador(?:a|es|as)?|limpieza)\b/',
            'oficina' => '/\b(?:oficina|administrativ[oa]s?)\b/'] as $canonical => $pattern) {
            if (preg_match($pattern, $role)) {
                $matches[] = $canonical;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function storedShift(array $shift): array
    {
        return $this->normalize(['source_id' => $shift['source_id'], 'fecha' => $shift['fecha'] ?? null,
            'empleado' => $shift['empleado_nombre'] ?? null, 'empleado_documento' => $shift['empleado_documento'] ?? null,
            'hora_inicio' => $shift['hora_inicio'] ?? null, 'hora_fin' => $shift['hora_fin'] ?? null,
            'duracion_minutos' => $shift['duracion_minutos'] ?? null,
            'puesto' => $shift['rol_necesario'] ?? null, 'archivo_origen' => $shift['archivo_origen'] ?? null,
            'paginas_origen' => $shift['paginas_origen'] ?? null,
            'archivo_sha256' => $shift['archivo_sha256'] ?? '',
            'puesto_documento' => $shift['puesto_documento'] ?? '', 'fichaje_entrada' => $shift['fichaje_entrada'] ?? '',
            'fichaje_salida' => $shift['fichaje_salida'] ?? '', 'notas_dia' => $shift['notas_dia'] ?? '',
            'datos_origen' => $shift['datos_origen'] ?? []], 'turno', 0);
    }

    private function snapshot(string $week, array $rows, ?int $generatedBy): array
    {
        usort($rows, static fn ($a, $b) => [$a['fecha'], $a['puesto'], $a['empleado'], $a['hora_inicio'] ?? '', $a['source_id']]
            <=> [$b['fecha'], $b['puesto'], $b['empleado'], $b['hora_inicio'] ?? '', $b['source_id']]);
        $startDate = (new DateTimeImmutable($rows[0]['fecha']))->modify('monday this week');
        $layout = [];
        foreach (self::DAYS as $offset => $day) {
            $layout[$day] = ['dia' => $day, 'fecha' => $startDate->modify('+'.$offset.' days')->format('Y-m-d'),
                'notas' => '', 'turnos_requeridos' => [], 'incidencias_importacion' => []];
        }
        $employees = $results = $positions = $notes = [];
        $shiftCount = 0;
        foreach ($rows as $row) {
            $day = self::DAYS[(int) (new DateTimeImmutable($row['fecha']))->format('N') - 1];
            $employeeId = 'hist_'.hash('sha256', Str::lower(Str::ascii($row['empleado'])).'|'.$row['empleado_documento']);
            $employees[$employeeId] ??= ['id' => $employeeId, 'nombre' => $row['empleado'],
                'rol' => $row['puesto'], 'empleado_documento' => $row['empleado_documento']];
            if ($row['tipo'] === 'incidencia') {
                $layout[$day]['incidencias_importacion'][] = $row;
                $notes[$day][$row['empleado'].': '.$row['notas_dia']] = true;
                continue;
            }
            $shiftId = 'hist_'.hash('sha256', $row['source_id']);
            $minutes = $row['duracion_minutos'];
            $duration = round($minutes / 60, 2);
            $positionKey = $day.'|'.$row['puesto'];
            $positions[$positionKey] = ($positions[$positionKey] ?? 0) + 1;
            $shift = ['turno_id' => $shiftId, 'source_id' => $row['source_id'], 'fecha' => $row['fecha'],
                'rol_necesario' => $row['puesto'], 'puesto_num' => $positions[$positionKey],
                'hora_inicio' => $row['hora_inicio'], 'hora_fin' => $row['hora_fin'], 'duracion_minutos' => $minutes, 'duracion_horas' => $duration,
                'empleado_id' => $employeeId, 'empleado_nombre' => $row['empleado']];
            foreach (['archivo_origen', 'archivo_sha256', 'paginas_origen', 'empleado_documento', 'puesto_documento', 'fichaje_entrada', 'fichaje_salida', 'notas_dia', 'datos_origen'] as $field) {
                $shift[$field] = $row[$field];
            }
            $layout[$day]['turnos_requeridos'][] = $shift;
            $results[] = ['turno_id' => $shiftId, 'empleado_id' => $employeeId, 'nombre' => $row['empleado'],
                'hora_inicio' => $row['hora_inicio'], 'hora_fin' => $row['hora_fin'], 'duracion_minutos' => $minutes, 'duracion_horas' => $duration];
            $shiftCount++;
            if ($row['notas_dia'] !== '') {
                $notes[$day][$row['notas_dia']] = true;
            }
        }
        foreach ($layout as $day => &$dayLayout) {
            $dayLayout['notas'] = implode('; ', array_keys($notes[$day] ?? []));
        }
        unset($dayLayout);

        return ['semana_id' => $week, 'fecha_inicio' => $startDate->format('Y-m-d'),
            'fecha_fin' => $startDate->modify('+6 days')->format('Y-m-d'), 'plantilla_diseno' => array_values($layout),
            'resultados' => $results, 'empleados_snapshot' => array_values($employees),
            'turnos_cubiertos' => $shiftCount, 'turnos_totales' => $shiftCount, 'generado_por' => $generatedBy,
            'estado' => 'historico', 'vigente' => false, 'roles_publicados' => [], 'draft_slot' => null, 'version' => 1];
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    private function canonicalData(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonicalData($item);
            }
        }
        unset($item);

        return $value;
    }
}
