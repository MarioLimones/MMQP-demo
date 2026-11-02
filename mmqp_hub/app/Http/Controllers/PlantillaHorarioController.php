<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlantillaHorarioController extends Controller
{
    public function index()
    {
        return response()->json(\App\Models\PlantillaHorario::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:plantilla_horarios',
            'turnos' => 'required|array',
        ]);

        $plantilla = \App\Models\PlantillaHorario::create([
            'nombre' => $request->nombre,
            'turnos' => $request->turnos,
        ]);

        return response()->json($plantilla, 201);
    }

    public function destroy($id)
    {
        $plantilla = \App\Models\PlantillaHorario::findOrFail($id);
        $plantilla->delete();
        return response()->json(['message' => 'Plantilla eliminada']);
    }
}
