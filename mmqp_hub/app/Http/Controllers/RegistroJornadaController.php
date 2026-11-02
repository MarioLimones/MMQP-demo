<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Fichaje;
use Carbon\Carbon;

class RegistroJornadaController extends Controller
{
    public function index()
    {
        $empleados = User::employees()->orderBy('nombre')->get();
        return view('admin.registro-jornada.index', compact('empleados'));
    }

    public function generarInforme(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'empleado_id' => 'nullable|exists:users,id',
            'tipo_informe' => 'nullable|in:normal,resumido,fichaje'
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();
        $tipoInforme = $request->tipo_informe ?? 'normal';

        $query = User::employees()->orderBy('nombre');
        
        if ($request->empleado_id) {
            $query->where('id', $request->empleado_id);
        }

        $empleados = $query->with(['fichajes' => function ($q) use ($fechaInicio, $fechaFin) {
            $q->betweenLocalDates($fechaInicio->toDateString(), $fechaFin->toDateString())
              ->orderBy('entrada', 'asc');
        }])->get();

        if ($tipoInforme === 'resumido') {
            return $this->generarInformeResumido($empleados, $fechaInicio, $fechaFin);
        } elseif ($tipoInforme === 'fichaje') {
            return $this->generarInformeFichaje($empleados, $fechaInicio, $fechaFin);
        }

        // Procesar los datos para la vista normal
        $reporte = [];
        foreach ($empleados as $empleado) {
            $fichajesAgrupados = [];
            foreach ($empleado->fichajes as $fichaje) {
                $fechaStr = $fichaje->localEntry()->format('Y-m-d');
                if (!isset($fichajesAgrupados[$fechaStr])) {
                    $fichajesAgrupados[$fechaStr] = [];
                }
                $fichajesAgrupados[$fechaStr][] = $fichaje;
            }

            // Crear una lista de días
            $dias = [];
            $periodo = \Carbon\CarbonPeriod::create($fechaInicio, $fechaFin);
            
            foreach ($periodo as $fecha) {
                $fechaStr = $fecha->format('Y-m-d');
                $fichajesDia = $fichajesAgrupados[$fechaStr] ?? [];

                if (count($fichajesDia) > 0) {
                    foreach ($fichajesDia as $f) {
                        $dias[] = [
                            'fecha' => $fecha->format('d/m/Y'),
                            'entrada' => $f->entrada ? $f->localEntry()->format('H:i') : '-',
                            'salida' => $f->salida ? $f->localExit()->format('H:i') : '-',
                            'horas_ordinarias' => $f->horas_calculadas ?? 0,
                            'horas_extra' => 0,
                        ];
                    }
                } else {
                    $dias[] = [
                        'fecha' => $fecha->format('d/m/Y'),
                        'entrada' => '',
                        'salida' => '',
                        'horas_ordinarias' => 0,
                        'horas_extra' => 0,
                    ];
                }
            }

            $reporte[] = [
                'empleado' => $empleado,
                'dias' => $dias,
                'total_ordinarias' => collect($dias)->sum('horas_ordinarias'),
            ];
        }

        $mesReporte = $fechaInicio->translatedFormat('F Y');
        
        return view('admin.registro-jornada.informe', compact('reporte', 'fechaInicio', 'fechaFin', 'mesReporte'));
    }

    private function generarInformeResumido($empleados, $fechaInicio, $fechaFin)
    {
        $reporte = [];
        foreach ($empleados as $empleado) {
            $totalHoras = 0;
            foreach ($empleado->fichajes as $fichaje) {
                $totalHoras += $fichaje->horas_calculadas ?? 0;
            }
            $reporte[] = [
                'empleado' => $empleado,
                'total_horas' => $totalHoras
            ];
        }

        return view('admin.registro-jornada.informe-resumido', compact('reporte', 'fechaInicio', 'fechaFin'));
    }

    private function generarInformeFichaje($empleados, $fechaInicio, $fechaFin)
    {
        $fichajesTotales = [];
        foreach ($empleados as $empleado) {
            foreach ($empleado->fichajes as $fichaje) {
                $fichajesTotales[] = [
                    'empleado' => $empleado,
                    'fecha_real' => $fichaje->entrada,
                    'fecha' => $fichaje->entrada ? $fichaje->localEntry()->format('d/m/Y') : '-',
                    'entrada' => $fichaje->entrada ? $fichaje->localEntry()->format('H:i') : '-',
                    'salida' => $fichaje->salida ? $fichaje->localExit()->format('H:i') : '-',
                    'horas' => $fichaje->horas_calculadas ?? 0
                ];
            }
        }

        // Ordenar por fecha cronológicamente
        usort($fichajesTotales, function($a, $b) {
            return $a['fecha_real'] <=> $b['fecha_real'];
        });

        return view('admin.registro-jornada.informe-fichaje', compact('fichajesTotales', 'fechaInicio', 'fechaFin'));
    }
}
