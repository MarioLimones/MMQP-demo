<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCuadreRequest;
use App\Models\CuadreCaja;
use App\Services\CuadreCalculator;
use App\Services\CuadreConflict;
use App\Services\SaveCuadre;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CuadreCajaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['fecha' => 'sometimes|required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:9999-12-31', 'turno' => 'sometimes|required|in:apertura,intermedio,cierre']);
        $fecha = $request->input('fecha', Carbon::today()->toDateString());
        $turno = $request->input('turno', 'cierre');
        $cuadre = CuadreCaja::with(['responsible', 'user'])->whereDate('fecha', $fecha)->where('turno', $turno)->first();
        if ($request->expectsJson()) {
            return response()->json(['cuadre' => $cuadre ? ['id' => $cuadre->id, 'version' => $cuadre->version, 'snapshot' => $cuadre->calculation_snapshot, 'legacy' => $cuadre->version === 0 ? $cuadre->revisions()->first()?->snapshot : null] : null]);
        }

        return view('admin.cuadre.index', compact('fecha', 'turno', 'cuadre'));
    }

    public function historial(Request $request)
    {
        $month = $request->input('month', Carbon::today()->format('Y-m'));
        try {
            if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new \InvalidArgumentException('Mes inválido.');
            }
            // Reset the day before parsing to avoid overflowing short months on day 31.
            $startDate = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            $month = Carbon::today()->format('Y-m');
            $startDate = Carbon::today()->startOfMonth();
        }
        $endDate = $startDate->copy()->addMonth();

        $historial = CuadreCaja::with(['user', 'responsible'])
            // SQLite can retain the cast's time component even in a DATE column.
            ->where('fecha', '>=', $startDate->toDateString())
            ->where('fecha', '<', $endDate->toDateString())
            ->orderByDesc('fecha')
            ->orderByRaw('CASE turno WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 0 END', [
                'cierre', 'intermedio', 'apertura',
            ])
            ->get();
        // Only logical closures, never revisions; deposits only occur in the cierre shift.
        $ingresoCents = 0;
        $legacyCount = 0;
        $legacyCents = 0;
        $legacyMissing = 0;
        foreach ($historial->where('turno', 'cierre') as $cuadre) {
            if ($cuadre->version === 0) {
                $legacyCount++;
                $original = $cuadre->ingreso_real;
                if ($original !== null && preg_match('/^-?\d{1,8}(?:\.\d{1,2})?$/D', $original)) {
                    $legacyCents += (str_starts_with($original, '-') ? -1 : 1) * CuadreCalculator::cents(ltrim($original, '-'));
                } else {
                    $legacyMissing++;
                }
            } else {
                $ingresoCents += $cuadre->calculation_snapshot['result_cents']['ingreso_real'];
            }
        }
        $summary = ['cierres' => $historial->where('turno', 'cierre')->count(), 'ingreso_calculado' => CuadreCalculator::decimal($ingresoCents),
            'ingreso_legacy' => CuadreCalculator::decimal($legacyCents), 'ingreso_total' => CuadreCalculator::decimal($ingresoCents + $legacyCents),
            'cierres_legacy' => $legacyCount, 'legacy_sin_importe' => $legacyMissing];
        if ($request->expectsJson()) {
            return response()->json(['summary' => $summary, 'cuadres' => $historial->map(fn ($c) => ['id' => $c->id, 'fecha' => $c->fecha->toDateString(), 'turno' => $c->turno, 'version' => $c->version, 'snapshot' => $c->calculation_snapshot])]);
        }

        return view('admin.cuadre.historial', compact('historial', 'month', 'startDate', 'summary'));
    }

    public function revisions(Request $request, CuadreCaja $cuadre)
    {
        $revisions = $cuadre->revisions()->paginate(25);
        if ($request->expectsJson()) {
            return response()->json($revisions);
        }

        return view('admin.cuadre.revisions', compact('cuadre', 'revisions'));
    }

    public function store(StoreCuadreRequest $request, SaveCuadre $service)
    {
        try {
            $revision = $service->save($request->validated(), $request->user());
        } catch (CuadreConflict $conflict) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $conflict->getMessage(), 'current_version' => $conflict->currentVersion], 409);
            }
            $cuadre = CuadreCaja::whereDate('fecha', $request->fecha)->where('turno', $request->turno)->first();
            // Render this rejected draft only in this response. Flashing it to the next
            // request would put one tab's stale values into another tab's fresh form.
            $request->session()->now('_old_input', $request->input());

            return response()->view('admin.cuadre.index', ['fecha' => $request->fecha, 'turno' => $request->turno, 'cuadre' => $cuadre, 'conflict' => $conflict->getMessage()], 409);
        }
        $receipt = ['id' => $revision->cuadre_id, 'version' => $revision->version, 'results' => $revision->snapshot['results']];
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cuadre guardado correctamente.', 'saved' => $receipt, 'snapshot' => $revision->snapshot]);
        }

        return redirect()->route('admin.cuadre', ['fecha' => $request->fecha, 'turno' => $request->turno])
            ->with('status', 'Cuadre guardado correctamente.')->with('saved_cuadre', $receipt);
    }
}
