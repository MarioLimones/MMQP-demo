<?php

namespace App\Services;

use App\Models\HorarioSemanal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScheduleTransfer
{
    public const FORMAT = 'mmqp.schedule-backup';
    public const VERSION = 1;
    public const MAX_KB = 65536;

    public function exportRow(HorarioSemanal $schedule): array
    {
        return $this->withoutCredentials([
            'semana_id' => $schedule->semana_id,
            'fecha_inicio' => $schedule->fecha_inicio,
            'fecha_fin' => $schedule->fecha_fin,
            'plantilla_diseno' => $schedule->plantilla_diseno ?? [],
            'resultados' => $schedule->resultados ?? [],
            'empleados_snapshot' => $schedule->empleados_snapshot ?? [],
            'turnos_cubiertos' => (int) $schedule->turnos_cubiertos,
            'turnos_totales' => (int) $schedule->turnos_totales,
            'version' => $schedule->version,
            'created_at' => $schedule->created_at?->toIso8601String(),
            'published_at' => $schedule->published_at?->toIso8601String(),
        ]);
    }

    public function maxUploadKb(): int
    {
        $limits = [self::MAX_KB * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $value = trim(ini_get($setting));
            $bytes = (int) $value * match (strtolower(substr($value, -1))) {
                'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
            };
            if ($bytes > 0) {
                // Leave room for the multipart form envelope.
                $limits[] = $setting === 'post_max_size' ? max(1024, $bytes - 65536) : $bytes;
            }
        }

        return max(1, (int) floor(min($limits) / 1024));
    }

    public function import(array $input, int $adminId): array
    {
        if (($input['format'] ?? null) !== self::FORMAT || ($input['version'] ?? null) !== self::VERSION) {
            $this->invalid('Selecciona un JSON exportado desde el historial de horarios de MMQP (versión 1).');
        }
        if (!isset($input['schedules']) || !is_array($input['schedules']) || !array_is_list($input['schedules'])
            || count($input['schedules']) === 0 || count($input['schedules']) > 5000) {
            $this->invalid('El archivo debe contener entre 1 y 5000 horarios.');
        }

        // Validate every record before writing. No source database ID is restored.
        $rows = [];
        foreach ($input['schedules'] as $index => $row) {
            $normalized = $this->normalize($row, $index + 1);
            $fingerprint = array_intersect_key($normalized, array_flip([
                'semana_id', 'fecha_inicio', 'fecha_fin', 'plantilla_diseno', 'resultados', 'empleados_snapshot',
            ]));
            $hash = hash('sha256', json_encode($this->canonical($fingerprint), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $rows[] = $normalized + ['transfer_hash' => $hash];
        }

        return DB::transaction(function () use ($rows, $adminId) {
            $summary = ['imported' => 0, 'skipped' => 0];
            foreach ($rows as $row) {
                // The unique hash also handles simultaneous uploads on the deployed site.
                // This whitelist includes the source timestamp, which is not generally mass assignable.
                $schedule = HorarioSemanal::unguarded(fn () => HorarioSemanal::firstOrCreate(['transfer_hash' => $row['transfer_hash']], $row + [
                    'estado' => 'historico', 'vigente' => false, 'draft_slot' => null,
                    'roles_publicados' => [], 'generado_por' => $adminId,
                ]));
                $summary[$schedule->wasRecentlyCreated ? 'imported' : 'skipped']++;
            }

            return $summary;
        }, 3);
    }

    private function normalize(mixed $row, int $number): array
    {
        if (!is_array($row)) {
            $this->invalid("El horario {$number} no tiene una estructura válida.");
        }
        // The old designer stored a map of day names to shifts. Match its viewer's normalization.
        if (isset($row['plantilla_diseno']) && is_array($row['plantilla_diseno']) && !array_is_list($row['plantilla_diseno'])) {
            $layout = [];
            foreach ($row['plantilla_diseno'] as $day => $value) {
                if (!is_array($value)) {
                    $this->invalid("El diseño del horario {$number} no tiene una estructura válida.");
                }
                $entry = array_is_list($value) ? ['turnos_requeridos' => $value] : $value;
                $entry['dia'] ??= (string) $day;
                if (isset($entry['turnos_requeridos']) && !is_array($entry['turnos_requeridos'])) {
                    $this->invalid("Los turnos del horario {$number} no tienen una estructura válida.");
                }
                $entry['turnos_requeridos'] ??= [];
                foreach ($entry['turnos_requeridos'] as &$shift) {
                    if (is_array($shift)) {
                        $shift['turno_id'] ??= $shift['id'] ?? $shift['_idLocal'] ?? null;
                        $shift['rol_necesario'] ??= $shift['rol'] ?? null;
                    }
                }
                unset($shift);
                $layout[] = $entry;
            }
            $row['plantilla_diseno'] = $layout;
        }
        $rules = [
            'semana_id' => ['required', 'string', 'regex:/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/'],
            'fecha_inicio' => 'required|string|max:64', 'fecha_fin' => 'required|string|max:64',
            'plantilla_diseno' => 'present|array|list|max:7',
            'plantilla_diseno.*' => 'required|array',
            'plantilla_diseno.*.dia' => 'required|string|max:40',
            'plantilla_diseno.*.fecha' => 'nullable|string|max:100',
            'plantilla_diseno.*.notas' => 'nullable|string',
            'plantilla_diseno.*.turnos_requeridos' => 'present|array|list|max:5000',
            'resultados' => 'present|array|list|max:35000',
            'empleados_snapshot' => 'present|array|list|max:5000', 'empleados_snapshot.*' => 'required|array',
            'empleados_snapshot.*.id' => ['required', function ($attribute, $value, $fail) {
                if ((!is_string($value) && !is_int($value)) || strlen((string) $value) > 255) {
                    $fail('El identificador del empleado debe ser un número o un texto.');
                }
            }],
            'empleados_snapshot.*.nombre' => 'required|string|max:255',
            'empleados_snapshot.*.apellidos' => 'nullable|string|max:255',
            'empleados_snapshot.*.rol' => 'nullable|string|max:255',
            'turnos_cubiertos' => 'required|integer|min:0|max:35000',
            'turnos_totales' => 'required|integer|min:0|max:35000',
            'version' => 'sometimes|integer|min:1|max:2147483647',
            'created_at' => 'nullable|date', 'published_at' => 'nullable|date',
        ];
        $validator = Validator::make($row, $rules);
        if ($validator->fails()) {
            $this->invalid("Revisa el horario {$number}: ".$validator->errors()->first());
        }
        $week = CarbonImmutable::now()->setISODate((int) substr($row['semana_id'], 0, 4), (int) substr($row['semana_id'], 6))->startOfWeek();
        if ($week->format('o-\WW') !== $row['semana_id']) {
            $this->invalid("El horario {$number} no corresponde a una semana ISO válida.");
        }

        $employees = [];
        foreach ($row['empleados_snapshot'] as &$employee) {
            $oldId = (string) $employee['id'];
            if (isset($employees[$oldId])) {
                $this->invalid("El horario {$number} contiene empleados repetidos.");
            }
            $employees[$oldId] = str_starts_with($oldId, 'hist_') ? $oldId
                : 'hist_transfer_'.hash('sha256', $oldId.'|'.$employee['nombre'].'|'.($employee['apellidos'] ?? ''));
            $employee['id'] = $employees[$oldId];
        }
        unset($employee);

        $shifts = [];
        foreach ($row['plantilla_diseno'] as $day) {
            foreach ($day['turnos_requeridos'] as $shift) {
                $this->validateShift($shift, $number, true);
                if (isset($shifts[$shift['turno_id']])) {
                    $this->invalid("El horario {$number} contiene turnos repetidos.");
                }
                $shifts[$shift['turno_id']] = true;
            }
        }
        // Validate shifts in a linear pass. Expanding thousands of wildcard rules would
        // make an otherwise valid multi-year backup too slow to import over HTTP.
        foreach ($row['resultados'] as $result) {
            $this->validateShift($result, $number);
            if (array_key_exists('turnos_requeridos', $result)) {
                $group = $result['turnos_requeridos'];
                if (!is_array($group) || !array_is_list($group) || count($group) > 5000) {
                    $this->invalid("El horario {$number} contiene un grupo de resultados inválido.");
                }
                foreach ($group as $assignment) {
                    $this->validateShift($assignment, $number);
                }
            }
        }
        $row['plantilla_diseno'] = $this->remapAssignments($row['plantilla_diseno'], $employees, $number);
        $row['resultados'] = $this->remapAssignments($row['resultados'], $employees, $number);
        // Older schedules can store results grouped by day instead of flat assignments.
        $assigned = [];
        foreach ($row['resultados'] as $result) {
            foreach (isset($result['turnos_requeridos']) ? $result['turnos_requeridos'] : [$result] as $assignment) {
                if (!is_array($assignment) || empty($assignment['empleado_id'])) {
                    continue;
                }
                $id = $assignment['turno_id'] ?? null;
                if (!is_string($id) || !isset($shifts[$id]) || isset($assigned[$id])) {
                    $this->invalid("El horario {$number} contiene asignaciones sin turno o repetidas.");
                }
                $assigned[$id] = true;
            }
        }

        return $this->withoutCredentials(array_intersect_key($row, array_flip([
            'semana_id', 'fecha_inicio', 'fecha_fin', 'plantilla_diseno', 'resultados', 'empleados_snapshot',
            'turnos_cubiertos', 'turnos_totales', 'version',
        ])) + [
            'version' => 1,
            // ISO week is the authoritative year even for old labels such as "13 jul".
            'created_at' => isset($row['created_at']) ? CarbonImmutable::parse($row['created_at'])->utc()->format('Y-m-d H:i:s') : $week->format('Y-m-d H:i:s'),
            'published_at' => isset($row['published_at']) ? CarbonImmutable::parse($row['published_at'])->utc()->format('Y-m-d H:i:s') : null,
        ]);
    }

    private function validateShift(mixed $shift, int $number, bool $requireId = false): void
    {
        if (!is_array($shift) || ($requireId && empty($shift['turno_id']))) {
            $this->invalid("El horario {$number} contiene un turno sin identificador o una asignación inválida.");
        }
        foreach (['turno_id', 'rol_necesario', 'rol', 'nombre', 'empleado_nombre'] as $field) {
            if (isset($shift[$field]) && (!is_string($shift[$field]) || mb_strlen($shift[$field]) > 255)) {
                $this->invalid("Revisa el campo {$field} del horario {$number}: debe ser un texto.");
            }
        }
        foreach (['hora_inicio', 'hora_fin'] as $field) {
            $pattern = $field === 'hora_fin' ? '/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/' : '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
            if (($requireId && !isset($shift[$field])) || (isset($shift[$field]) && (!is_string($shift[$field]) || !preg_match($pattern, $shift[$field])))) {
                $this->invalid("Revisa el campo {$field} del horario {$number}: usa el formato HH:MM.");
            }
        }
        foreach (['duracion_horas', 'duracion_minutos'] as $field) {
            if (isset($shift[$field]) && (!is_numeric($shift[$field]) || !is_finite((float) $shift[$field]) || $shift[$field] < 0
                || ($field === 'duracion_minutos' && floor((float) $shift[$field]) != $shift[$field]))) {
                $this->invalid("Revisa el campo {$field} del horario {$number}: la duración debe ser un número positivo o cero.");
            }
        }
    }

    private function remapAssignments(array $value, array $employees, int $number): array
    {
        foreach ($value as $key => &$item) {
            if ($key === 'empleado_id' && $item !== null && $item !== '') {
                if ((!is_string($item) && !is_int($item)) || !isset($employees[(string) $item])) {
                    $this->invalid("El horario {$number} contiene una asignación sin empleado en su copia guardada.");
                }
                $item = $employees[(string) $item];
            } elseif (is_array($item)) {
                $item = $this->remapAssignments($item, $employees, $number);
            }
        }

        return $value;
    }

    private function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }

    private function withoutCredentials(array $value): array
    {
        unset($value['password'], $value['password_hash'], $value['remember_token'],
            $value['token'], $value['access_token'], $value['refresh_token']);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->withoutCredentials($item);
            }
        }

        return $value;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['archivo' => $message]);
    }
}
