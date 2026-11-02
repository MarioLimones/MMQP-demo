<?php

namespace App\Services;

use App\Models\HorarioSemanal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleDrafts
{
    public function authorize(): void
    {
        abort_unless(auth()->user()?->es_admin, 403);
    }

    public function open(string $week, ?int $sourceId = null): HorarioSemanal
    {
        $this->authorize();

        return DB::transaction(function () use ($week, $sourceId) {
            $date = Carbon::now()->setISODate((int) substr($week, 0, 4), (int) substr($week, 6))->startOfWeek();
            abort_unless(preg_match('/^\d{4}-W\d{2}$/', $week) && $date->format('o-\WW') === $week,
                422, 'La semana debe ser una semana ISO válida. El registro antiguo se conserva sin cambios.');
            $source = $sourceId ? HorarioSemanal::where('semana_id', $week)->findOrFail($sourceId) : null;
            $existing = HorarioSemanal::where('semana_id', $week)->where('draft_slot', 1)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $source ??= HorarioSemanal::vigentes()->where('semana_id', $week)->latest('id')->lockForUpdate()->first();

            // firstOrCreate recovers a simultaneous insert via the database unique constraint.
            return HorarioSemanal::firstOrCreate(['semana_id' => $week, 'draft_slot' => 1], [
                'estado' => 'borrador', 'version' => 1, 'vigente' => false,
                'fecha_inicio' => $source?->fecha_inicio ?? $date->format('Y-m-d'),
                'fecha_fin' => $source?->fecha_fin ?? $date->copy()->addDays(6)->format('Y-m-d'),
                'plantilla_diseno' => $source?->plantilla_diseno ?? [],
                'resultados' => $source?->resultados ?? [],
                'empleados_snapshot' => $source?->empleados_snapshot ?? User::employees()->where('activo', true)
                    ->get(['id', 'nombre', 'apellidos', 'rol', 'horas_mensuales', 'en_baja', 'orden_visual'])->toArray(),
                'roles_publicados' => [], 'generado_por' => auth()->id(),
                'source_publicacion_id' => $source?->id,
                'turnos_cubiertos' => $source?->turnos_cubiertos ?? 0,
                'turnos_totales' => $source?->turnos_totales ?? 0,
            ]);
        }, 3);
    }

    public function requireDraft(int $id, string $week, int $version): HorarioSemanal
    {
        $this->authorize();
        $draft = HorarioSemanal::findOrFail($id);
        abort_unless($draft->semana_id === $week, 422, 'El borrador pertenece a otra semana.');
        $this->checkVersion($draft, $version);

        return $draft;
    }

    private function checkVersion(HorarioSemanal $draft, int $version): void
    {
        abort_if($draft->estado !== 'borrador' || $draft->version !== $version, 409,
            'El horario cambió en otra pestaña o ya se publicó. Descarga tus cambios y recarga para revisar la versión vigente.');
    }

    public function save(int $id, string $week, int $version, string $key, array $content): HorarioSemanal
    {
        $this->authorize();
        $hash = hash('sha256', json_encode([$version, $content]));

        return DB::transaction(function () use ($id, $week, $version, $key, $hash, $content) {
            if (DB::connection()->getDriverName() === 'sqlite') {
                // Reserve the writer before reading: SQLite ignores FOR UPDATE and a
                // deferred read-to-write upgrade can fail immediately with SQLITE_BUSY.
                // No rows change; other databases keep their row-locking behaviour.
                DB::update('UPDATE horarios_semanales SET version = version WHERE 1 = 0');
            }
            $draft = HorarioSemanal::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->semana_id === $week, 422, 'El borrador pertenece a otra semana.');
            // An identical retry is acknowledged only while this is still the same editable draft.
            if ($draft->estado === 'borrador' && $draft->last_save_key === $key && $draft->last_save_hash === $hash) {
                return $draft;
            }
            abort_if($draft->last_save_key === $key, 409, 'La clave de guardado ya se usó con otros cambios. Recarga para revisar.');
            $this->checkVersion($draft, $version);
            $values = $this->content($content, $week);
            $values += ['version' => $version + 1, 'last_save_key' => $key, 'last_save_hash' => $hash,
                'generado_por' => auth()->id(), 'updated_at' => now()];
            // Conditional UPDATE also protects databases where SELECT FOR UPDATE is unavailable (SQLite).
            $changed = HorarioSemanal::whereKey($id)->where('estado', 'borrador')->where('version', $version)
                ->update($this->encode($values));
            abort_unless($changed === 1, 409, 'Conflicto de versión. Recarga y revisa los cambios concurrentes.');

            return $draft->fresh();
        }, 3);
    }

    private function content(array $content, string $week): array
    {
        validator($content, [
            'fecha_inicio' => 'required|string', 'fecha_fin' => 'required|string',
            'empleados_snapshot' => 'present|array', 'plantilla_diseno' => 'present|array',
            'plantilla_diseno.*.dia' => 'required|string',
            'plantilla_diseno.*.turnos_requeridos' => 'present|array',
            'plantilla_diseno.*.turnos_requeridos.*.turno_id' => 'required|string',
            'plantilla_diseno.*.turnos_requeridos.*.rol_necesario' => 'required|in:encargado,repartidor,auxiliar,limpiadora,oficina',
            'plantilla_diseno.*.turnos_requeridos.*.hora_inicio' => 'required|date_format:H:i',
            'plantilla_diseno.*.turnos_requeridos.*.hora_fin' => 'required|date_format:H:i',
            'resultados' => 'sometimes|array', 'resultados.*.turno_id' => 'required|string',
            'resultados.*.empleado_id' => 'required',
        ])->validate();
        $layout = $content['plantilla_diseno'];
        $shifts = collect($layout)->flatMap(fn ($day) => $day['turnos_requeridos'] ?? []);
        $ids = $shifts->pluck('turno_id');
        if ($ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages(['plantilla_diseno' => 'Cada turno necesita un identificador único.']);
        }
        $results = $content['resultados'] ?? $shifts->filter(fn ($shift) => ! empty($shift['empleado_id']))
            ->map(fn ($shift) => ['turno_id' => $shift['turno_id'], 'empleado_id' => $shift['empleado_id'],
                'nombre' => $shift['empleado_nombre'] ?? '', 'hora_inicio' => $shift['hora_inicio'],
                'hora_fin' => $shift['hora_fin'], 'duracion_horas' => $shift['duracion_horas'] ?? 0])->values()->all();
        $assigned = collect($results)->pluck('turno_id');
        if ($assigned->unique()->count() !== $assigned->count() || $assigned->diff($ids)->isNotEmpty()) {
            throw ValidationException::withMessages(['resultados' => 'Las asignaciones deben corresponder a turnos únicos del diseño.']);
        }
        // Embedded editor assignments and flat results describe the same snapshot.
        $byShift = collect($results)->keyBy('turno_id');
        foreach ($layout as &$day) {
            foreach ($day['turnos_requeridos'] as &$shift) {
                $assignment = $byShift->get($shift['turno_id']);
                $shift['empleado_id'] = $assignment['empleado_id'] ?? null;
                $shift['empleado_nombre'] = $assignment['nombre'] ?? null;
            }
            unset($shift);
        }
        unset($day);

        app(HolidayAssignments::class)->validate($layout, $results, $week);

        return ['plantilla_diseno' => $layout, 'resultados' => $results,
            'empleados_snapshot' => $content['empleados_snapshot'],
            'fecha_inicio' => $content['fecha_inicio'], 'fecha_fin' => $content['fecha_fin'],
            'turnos_cubiertos' => count($results), 'turnos_totales' => $shifts->count()];
    }

    public function publish(int $id, string $week, int $version, array $roles): HorarioSemanal
    {
        $this->authorize();

        return DB::transaction(function () use ($id, $week, $version, $roles) {
            $draft = HorarioSemanal::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->semana_id === $week, 422);
            $this->checkVersion($draft, $version);
            // Validate the stored version too: direct API callers cannot publish malformed legacy content.
            $this->content($draft->only(['fecha_inicio', 'fecha_fin', 'plantilla_diseno', 'resultados', 'empleados_snapshot']), $week);
            // The draft ID is consumed. Every queued autosave for it will now fail.
            $changed = HorarioSemanal::whereKey($id)->where('estado', 'borrador')->where('version', $version)->update([
                'estado' => 'published', 'draft_slot' => null, 'vigente' => true,
                'roles_publicados' => json_encode(array_values(array_unique($roles))),
                'version' => $version + 1, 'published_draft_version' => $version,
                'published_at' => now(), 'updated_at' => now(),
            ]);
            abort_unless($changed === 1, 409, 'Conflicto al publicar. Recarga y revisa el borrador.');
            // Republication replaces visibility, while keeping all old snapshots and relationships.
            HorarioSemanal::where('semana_id', $week)->where('estado', '!=', 'borrador')
                ->where('id', '!=', $id)->update(['vigente' => false]);

            return $draft->fresh();
        }, 3);
    }

    public function delete(int $id, string $week, int $version): void
    {
        $this->authorize();
        $this->requireDraft($id, $week, $version);
        abort_unless(HorarioSemanal::whereKey($id)->where('semana_id', $week)->where('estado', 'borrador')
            ->where('version', $version)->delete() === 1, 409);
    }

    private function encode(array $values): array
    {
        foreach (['plantilla_diseno', 'resultados', 'empleados_snapshot'] as $field) {
            $values[$field] = json_encode($values[$field], JSON_THROW_ON_ERROR);
        }

        return $values;
    }
}
