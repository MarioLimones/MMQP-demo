<?php

namespace App\Http\Controllers;

use App\Models\Festivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class HolidayCalendarController extends Controller
{
    public function index(): View
    {
        return view('admin.festivos.index', ['festivos' => Festivo::query()->orderBy('fecha')->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'festivos' => 'sometimes|array|max:1000',
            'festivos.*' => 'array:fecha,nombre,eliminar',
            'festivos.*.eliminar' => 'sometimes|boolean',
            'nuevo' => 'sometimes|array:fecha,nombre',
        ]);
        $rows = collect($data['festivos'] ?? [])->reject(fn ($row) => ! empty($row['eliminar']))->values()->all();
        if ($request->filled('nuevo.fecha') || $request->filled('nuevo.nombre')) {
            $rows[] = $data['nuevo'];
        }
        $validated = Validator::make(['festivos' => $rows], [
            'festivos' => 'array|max:1000',
            'festivos.*.fecha' => 'required|date_format:Y-m-d|distinct',
            'festivos.*.nombre' => 'required|string|max:120',
        ], [
            'festivos.*.fecha.distinct' => 'Cada fecha festiva debe aparecer una sola vez.',
        ])->validate();

        DB::transaction(function () use ($validated) {
            Festivo::query()->delete();
            foreach ($validated['festivos'] as $row) {
                Festivo::create(['fecha' => $row['fecha'], 'nombre' => trim($row['nombre'])]);
            }
        });

        return redirect()->route('admin.festivos.index')->with('success', 'Calendario de festivos actualizado.');
    }
}
