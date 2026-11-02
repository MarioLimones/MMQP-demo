<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SolicitudAusencia;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AbsenceService;
use App\Services\AbsenceAvailability;
use App\Services\PublishedAbsenceConflicts;

class SolicitudController extends Controller
{
    public function __construct(private AbsenceService $absences) {}

    public function index()
    {
        $solicitudes = SolicitudAusencia::with('user')->whereHas('user', fn ($query) => $query->employees())
            ->orderByRaw('CASE WHEN estado = ? THEN 1 ELSE 0 END DESC', ['pendiente'])
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc') // Stable pagination when status and date are equal.
            ->paginate(20);

        return view('admin.solicitudes.index', compact('solicitudes'));
    }

    public function update(Request $request, SolicitudAusencia $solicitud)
    {
        $updated = $this->absences->transition($request->user(), $solicitud->id, $this->version($request), $request->all());
        return $this->result($updated, 'Solicitud actualizada correctamente.');
    }

    public function calendario()
    {
        // Solo obtener pendientes y aprobadas para no ensuciar el calendario con rechazadas
        $solicitudes = SolicitudAusencia::with('user')->whereHas('user', fn ($query) => $query->employees())
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->get();

        $eventos = [];
        foreach ($solicitudes as $sol) {
            if ($sol->estado === 'aprobada') {
                if ($sol->tipo === 'vacaciones') $color = '#3b82f6'; // Azul
                elseif ($sol->tipo === 'baja') $color = '#ef4444'; // Rojo
                else $color = '#10b981'; // Verde para dia_libre
            } else {
                $color = '#f59e0b'; // Naranja
            }

            $end = null;
            if ($sol->fecha_fin && $sol->fecha_fin->gt($sol->fecha)) {
                $end = $sol->fecha_fin->copy()->addDay()->format('Y-m-d');
            }

            $eventData = [
                'id' => $sol->id,
                'title' => $sol->user->nombre . ' ' . $sol->user->apellidos . ' - ' . ucfirst(str_replace('_', ' ', $sol->tipo)),
                'start' => $sol->fecha->format('Y-m-d'),
                'allDay' => true,
                'color' => $color,
                'extendedProps' => [
                    'user_id' => $sol->user_id,
                    'fecha' => $sol->fecha->format('Y-m-d'),
                    'fecha_fin' => $sol->fecha_fin ? $sol->fecha_fin->format('Y-m-d') : $sol->fecha->format('Y-m-d'),
                    'tipo' => $sol->tipo,
                    'motivo' => $sol->motivo_empleado,
                    'estado' => $sol->estado,
                    'version' => $sol->version,
                    'nombre' => $sol->user->nombre . ' ' . $sol->user->apellidos
                ]
            ];

            if ($end) {
                $eventData['end'] = $end;
            }

            $eventos[] = $eventData;
        }

        $empleados = User::employees()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'apellidos']);

        $historicalUsers = User::employees()->with(AbsenceAvailability::RELATIONS)->get();
        foreach ($historicalUsers as $user) {
            foreach (app(AbsenceAvailability::class)->historicalDates($user) as $date) {
                $eventos[] = ['id' => 'historical-'.$user->id.'-'.$date,
                    'title' => trim($user->nombre.' '.$user->apellidos).' - Bloqueo histórico',
                    'start' => $date, 'allDay' => true, 'color' => '#64748b',
                    'extendedProps' => ['historical' => true]];
            }
        }
        $historicalCount = $historicalUsers
            ->sum(fn ($user) => count(app(AbsenceAvailability::class)->unresolvedDates($user)));
        return view('admin.solicitudes.calendario', compact('eventos', 'empleados', 'historicalCount'));
    }

    public function storeManual(Request $request)
    {
        $absence = $this->absences->create($request->user(), $request->all());
        return $this->result($absence, $absence->trashed() ? 'Envío ya procesado; el registro eliminado no se ha reactivado.' : 'Ausencia asignada correctamente.');
    }

    public function updateManual(Request $request, $id)
    {
        $absence = $this->absences->edit($request->user(), (int) $id, $this->version($request), $request->all());
        return $this->result($absence, 'Ausencia actualizada correctamente.');
    }

    public function destroyManual(Request $request, $id)
    {
        $absence = $this->absences->delete($request->user(), (int) $id, $this->version($request));
        return $this->result($absence, 'Ausencia eliminada correctamente.');
    }

    private function version(Request $request): int
    {
        return (int) $request->validate(['version' => 'required|integer|min:1'])['version'];
    }

    private function result(SolicitudAusencia $absence, string $message)
    {
        $conflicts = app(PublishedAbsenceConflicts::class)->forUser($absence->user()->firstOrFail());
        return back()->with('success', $message)->with('absence_conflicts', $conflicts);
    }
}
