<?php

namespace App\Http\Controllers;

use App\Models\HorarioSemanal;
use App\Services\ScheduleTransfer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

class ScheduleTransferController extends Controller
{
    public function export(ScheduleTransfer $transfer, ?int $id = null)
    {
        $schedule = $id === null ? null : HorarioSemanal::findOrFail($id);
        $filename = 'horarios-'.($schedule ? $schedule->semana_id.'-'.$schedule->id : 'todos').'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($schedule, $transfer) {
            $options = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            echo '{"format":'.json_encode(ScheduleTransfer::FORMAT).',"version":'.ScheduleTransfer::VERSION
                .',"exported_at":'.json_encode(now()->toIso8601String()).',"schedules":[';
            $first = true;
            $query = HorarioSemanal::query()->when($schedule, fn ($query) => $query->whereKey($schedule->id));
            foreach ($query->lazyById(20) as $row) {
                echo ($first ? '' : ',')."\n".json_encode($transfer->exportRow($row), $options);
                $first = false;
            }
            echo "\n]}";
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function import(Request $request, ScheduleTransfer $transfer)
    {
        $request->validate(['archivo' => ['required', 'file', 'max:'.$transfer->maxUploadKb(), 'extensions:json']], [
            'archivo.required' => 'Selecciona el archivo JSON que has exportado desde MMQP.',
            'archivo.uploaded' => 'El archivo supera el tamaño que admite el servidor. Exporta horarios por separado o aumenta el límite de subida.',
            'archivo.max' => 'El archivo supera el límite de subida. Exporta horarios por separado.',
            'archivo.extensions' => 'Selecciona un archivo con extensión .json exportado desde MMQP.',
        ]);
        try {
            $input = json_decode($request->file('archivo')->get(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene un JSON válido. Vuelve a exportarlo desde MMQP.']);
        }
        if (!is_array($input)) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene una copia de horarios de MMQP.']);
        }
        try {
            $summary = $transfer->import($input, (int) $request->user()->id);
        } catch (JsonException) {
            throw ValidationException::withMessages(['archivo' => 'El archivo contiene valores que no son compatibles con una copia de horarios. Vuelve a exportarlo desde MMQP.']);
        }

        return redirect()->route('admin.horarios.historial')->with('success',
            "Importación completada: {$summary['imported']} horarios guardados en el historial; {$summary['skipped']} ya importados, omitidos.");
    }
}
