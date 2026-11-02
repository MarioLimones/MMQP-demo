<?php

namespace App\Services;

use App\Models\SolicitudAusencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AbsenceService
{
    public function dates(array $input, bool $employeeRequest = false): array
    {
        $data = Validator::make($input, [
            'fecha' => 'required|date_format:Y-m-d'.($employeeRequest ? '|after_or_equal:today' : ''),
            'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha',
            'tipo' => 'required|in:vacaciones,dia_libre,baja',
            'motivo_empleado' => 'nullable|string|max:255',
        ])->validate();
        $data['fecha_fin'] = $data['fecha_fin'] ?? $data['fecha'];

        return $data;
    }

    public function create(User $actor, array $input, bool $employeeRequest = false): SolicitudAusencia
    {
        abort_unless($employeeRequest || $actor->es_admin, 403);
        if ($employeeRequest) {
            $input['user_id'] = $actor->id;
            $input['tipo'] = 'dia_libre';
            $input['fecha_fin'] = $input['fecha'] ?? null;
        }
        $identity = Validator::make($input, [
            'user_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->whereNot('rol', 'admin')],
            'request_key' => 'required|uuid',
        ])->validate();
        $data = $this->dates($input, $employeeRequest);
        $data['user_id'] = (int) $identity['user_id'];
        $data['motivo_empleado'] = $data['motivo_empleado'] ?? ($employeeRequest ? null : 'Asignado por administrador');
        $data['estado'] = $employeeRequest ? 'pendiente' : 'aprobada';
        $data['razon_admin'] = $employeeRequest ? null : 'Asignación manual';
        ksort($data);
        $key = hash('sha256', $actor->id.':'.strtolower($identity['request_key']));
        $hash = hash('sha256', json_encode($data));

        return DB::transaction(function () use ($data, $key, $hash, $employeeRequest) {
            $this->lockUser($data['user_id']);
            $previous = SolicitudAusencia::withTrashed()->where('request_key', $key)->lockForUpdate()->first();
            if ($previous) {
                abort_unless(hash_equals($previous->request_hash, $hash), 409, 'Este envío ya se utilizó con otros datos. Recarga el formulario.');

                return $previous;
            }
            if ($employeeRequest && SolicitudAusencia::where('user_id', $data['user_id'])->whereDate('fecha', $data['fecha'])->exists()) {
                throw ValidationException::withMessages(['fecha' => 'Ya has solicitado este día libre.']);
            }

            return SolicitudAusencia::create($data + ['request_key' => $key, 'request_hash' => $hash]);
        }, 5);
    }

    public function edit(User $actor, int $id, int $version, array $input): SolicitudAusencia
    {
        abort_unless($actor->es_admin, 403);
        $data = $this->dates($input);

        return $this->mutate($id, $version, function ($absence) use ($data) {
            $absence->fill($data);
        });
    }

    public function transition(User $actor, int $id, int $version, array $input): SolicitudAusencia
    {
        abort_unless($actor->es_admin, 403);
        $data = Validator::make($input, [
            'estado' => 'required|in:aprobada,rechazada,cancelada',
            'razon_admin' => 'nullable|string|max:255',
        ])->validate();

        return $this->mutate($id, $version, function ($absence) use ($data) {
            // Validate the stored range too; approval must never activate malformed historical data.
            $this->dates([
                'fecha' => substr((string) $absence->getRawOriginal('fecha'), 0, 10),
                'fecha_fin' => $absence->getRawOriginal('fecha_fin') === null ? null : substr((string) $absence->getRawOriginal('fecha_fin'), 0, 10),
                'tipo' => $absence->tipo,
            ]);
            $absence->fill($data);
        });
    }

    public function delete(User $actor, int $id, int $version): SolicitudAusencia
    {
        abort_unless($actor->es_admin, 403);

        return $this->mutate($id, $version, fn ($absence) => $absence->deleted_at = now(), true);
    }

    private function mutate(int $id, int $version, callable $change, bool $deleting = false): SolicitudAusencia
    {
        $owner = SolicitudAusencia::withTrashed()->findOrFail($id)->user_id;

        return DB::transaction(function () use ($id, $owner, $version, $change, $deleting) {
            $this->lockUser($owner);
            $absence = SolicitudAusencia::withTrashed()->findOrFail($id);
            if ($deleting && $absence->trashed() && $absence->version === $version + 1) {
                return $absence;
            }
            abort_if($absence->trashed(), 404);
            abort_unless($absence->version === $version, 409, 'Otra operación ha modificado esta ausencia. Recarga la página antes de guardar.');
            $change($absence);
            if ($absence->isDirty()) {
                $absence->version++;
                $absence->save();
            }

            return $absence;
        }, 5);
    }

    public function lockUser(int $id): User
    {
        // Acquire a write lock before reading. Also serializes writers on SQLite, where FOR UPDATE is ignored.
        User::employees()->whereKey($id)->increment('absence_write_lock');

        return User::employees()->lockForUpdate()->findOrFail($id);
    }
}
