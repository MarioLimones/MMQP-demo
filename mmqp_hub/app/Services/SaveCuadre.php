<?php

namespace App\Services;

use App\Models\CuadreCaja;
use App\Models\CuadreRevision;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SaveCuadre
{
    public function __construct(private CuadreCalculator $calculator) {}

    public function save(array $validated, User $actor): CuadreRevision
    {
        $inputs = $this->calculator->normalize($validated);
        $requestId = $inputs['request_id'];
        // Canonical denomination order, money and version make HTML/JSON retries equivalent.
        $hash = hash('sha256', json_encode([$actor->id, $inputs], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($inputs, $actor, $requestId, $hash) {
                if (DB::connection()->getDriverName() === 'sqlite') {
                    // Reserve SQLite's single writer before any reads. A no-op UPDATE avoids
                    // deferred read-to-write lock upgrades without altering other modules' connection config.
                    DB::update('UPDATE cuadre_cajas SET version = version WHERE 1 = 0');
                }
                if ($revision = CuadreRevision::where('request_id', $requestId)->first()) {
                    return $this->replay($revision, $hash);
                }
                $cuadre = CuadreCaja::whereDate('fecha', $inputs['fecha'])->where('turno', $inputs['turno'])->lockForUpdate()->first();
                if ($revision = CuadreRevision::where('request_id', $requestId)->lockForUpdate()->first()) {
                    return $this->replay($revision, $hash);
                }
                if (($cuadre?->version ?? 0) !== $inputs['expected_version']) {
                    throw new CuadreConflict($cuadre?->version);
                }
                // Version 0 is also an imported record; absence is determined by existence, not version.
                $before = $cuadre ? $cuadre->revisions()->where('version', $cuadre->version)->firstOrFail()->snapshot : null;
                $employee = User::employees()->where(fn ($query) => $query->where('dni', $inputs['dni_empleado'])->orWhereRaw('UPPER(dni) LIKE ?', [$inputs['dni_empleado'].'_']))->get()
                    ->filter(fn ($user) => preg_match('/^'.preg_quote($inputs['dni_empleado'], '/').'[A-Za-z]?$/D', $user->dni))->sole();
                $snapshot = $this->calculator->calculate($inputs);
                $snapshot['responsible'] = ['id' => $employee->id, 'nombre' => $employee->nombre, 'apellidos' => $employee->apellidos, 'dni' => $employee->dni];
                $version = ($cuadre?->version ?? 0) + 1;
                $attributes = array_intersect_key($inputs, array_flip(['fecha', 'turno', 'billetes', 'monedas', 'billetes_ingreso', 'monedas_ingreso', 'blisters', 'blisters_ingreso', ...CuadreCalculator::MONEY_FIELDS]));
                $attributes += array_intersect_key($snapshot['results'], array_flip(['total_calculado', 'ingreso_real', 'descuadre']));
                $attributes += ['version' => $version, 'calculation_snapshot' => $snapshot, 'responsible_id' => $employee->id];
                if ($cuadre) {
                    // Compare-and-swap is necessary even when SQLite ignores lockForUpdate().
                    $encoded = $attributes;
                    foreach (['billetes', 'monedas', 'billetes_ingreso', 'monedas_ingreso', 'blisters', 'blisters_ingreso', 'calculation_snapshot'] as $field) {
                        $encoded[$field] = $encoded[$field] === null ? null : json_encode($encoded[$field], JSON_THROW_ON_ERROR);
                    }
                    $encoded['updated_at'] = now('UTC');
                    if (CuadreCaja::whereKey($cuadre->id)->where('version', $inputs['expected_version'])->update($encoded) !== 1) {
                        throw new CuadreConflict(CuadreCaja::find($cuadre->id)?->version);
                    }
                } else {
                    $cuadre = CuadreCaja::create($attributes + ['created_by' => $actor->id]);
                }

                return CuadreRevision::create(['cuadre_id' => $cuadre->id, 'version' => $version,
                    'origin' => $before === null ? 'creation' : 'correction', 'actor_id' => $actor->id,
                    'actor_snapshot' => ['id' => $actor->id, 'nombre' => $actor->nombre, 'apellidos' => $actor->apellidos],
                    'recorded_at' => now('UTC'), 'motivo' => $inputs['motivo'], 'request_id' => $requestId,
                    'request_hash' => $hash, 'before_snapshot' => $before, 'snapshot' => $snapshot]);
            }, 5);
        } catch (UniqueConstraintViolationException $error) {
            // A simultaneous creation/retry may lose the unique-index race. Its transaction is already rolled back.
            if ($revision = CuadreRevision::where('request_id', $requestId)->first()) {
                return $this->replay($revision, $hash);
            }
            $current = CuadreCaja::whereDate('fecha', $inputs['fecha'])->where('turno', $inputs['turno'])->first();
            if ($current) {
                throw new CuadreConflict($current->version);
            }
            throw $error;
        }
    }

    private function replay(CuadreRevision $revision, string $hash): CuadreRevision
    {
        if (! hash_equals($revision->request_hash, $hash)) {
            throw new CuadreConflict(null, 'Este identificador de envío ya se utilizó con otros datos. Recarga el formulario y revisa la versión vigente.');
        }

        return $revision;
    }
}
