<?php

namespace App\Console\Commands;

use App\Models\HorarioSemanal;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ExportarEntrenamientoHorarios extends Command
{
    protected $signature = 'horarios:exportar-entrenamiento {salida : Archivo JSON de entrenamiento}
        {--force : Permitir reemplazar el archivo de salida existente}';

    protected $description = 'Exporta horarios históricos documentales para entrenar la autoplantilla, sin modificar la base de datos.';

    public function handle(): int
    {
        $temporary = null;
        try {
            $path = (string) $this->argument('salida');
            $directory = dirname($path);
            if (! is_dir($directory) || ! is_writable($directory)) {
                throw new RuntimeException('La carpeta de salida debe existir y permitir escritura.');
            }
            if (file_exists($path) && ! $this->option('force')) {
                throw new RuntimeException('El archivo de salida ya existe; use --force para reemplazarlo.');
            }
            // A user-selected destination must never replace a source database or an application file.
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
                throw new RuntimeException('La salida debe ser un archivo .json.');
            }

            $shifts = [];
            $incidents = $schedules = 0;
            foreach (HorarioSemanal::where('estado', 'historico')->orderBy('id')->cursor() as $schedule) {
                $schedules++;
                foreach ($schedule->plantilla_diseno ?? [] as $day) {
                    $incidents += count($day['incidencias_importacion'] ?? []);
                    foreach ($day['turnos_requeridos'] ?? [] as $shift) {
                        $identity = $shift['empleado_id'] ?? null;
                        $shifts[] = [
                            'source_id' => $shift['source_id'] ?? null,
                            'fecha' => $shift['fecha'] ?? $day['fecha'] ?? null,
                            'puesto' => $shift['rol_necesario'] ?? null,
                            'hora_inicio' => $shift['hora_inicio'] ?? null,
                            'hora_fin' => $shift['hora_fin'] ?? null,
                            'duracion_minutos' => $shift['duracion_minutos'] ?? null,
                            // Keep stored identities separate; names never establish a training identity.
                            'empleado_id' => is_string($identity) || is_int($identity)
                                ? 'anon_'.hash('sha256', (string) $identity) : null,
                            'archivo_origen' => $shift['archivo_origen'] ?? null,
                            'archivo_sha256' => $shift['archivo_sha256'] ?? null,
                            'paginas_origen' => $shift['paginas_origen'] ?? [],
                        ];
                    }
                }
            }
            if ($shifts === []) {
                throw new RuntimeException('No hay turnos históricos para exportar.');
            }
            usort($shifts, static fn ($a, $b) => [$a['fecha'], $a['source_id'], $a['empleado_id'], $a['hora_inicio'], $a['hora_fin']]
                <=> [$b['fecha'], $b['source_id'], $b['empleado_id'], $b['hora_inicio'], $b['hora_fin']]);

            $years = $roles = $sources = [];
            $dates = [];
            foreach ($shifts as $shift) {
                $date = $shift['fecha'];
                if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
                    $year = substr($date, 0, 4);
                    $years[$year] = ($years[$year] ?? 0) + 1;
                    $dates[] = $date;
                }
                $role = $shift['puesto'] ?? '(sin puesto)';
                $roles[$role] = ($roles[$role] ?? 0) + 1;
                $sourceKey = json_encode([$shift['archivo_origen'], $shift['archivo_sha256']], JSON_THROW_ON_ERROR);
                $sources[$sourceKey] ??= ['archivo_origen' => $shift['archivo_origen'],
                    'archivo_sha256' => $shift['archivo_sha256'], 'turnos' => 0];
                $sources[$sourceKey]['turnos']++;
            }
            ksort($years);
            ksort($roles);
            ksort($sources);
            $datasetJson = json_encode($shifts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $metadata = ['historical_schedules' => $schedules, 'exported_shifts' => count($shifts),
                'excluded_incidents' => $incidents, 'by_year' => $years, 'by_role' => $roles,
                'date_min' => $dates === [] ? null : min($dates), 'date_max' => $dates === [] ? null : max($dates),
                'source_files' => array_values($sources), 'dataset_sha256' => hash('sha256', $datasetJson)];
            $envelope = ['format' => 'mmqp.historical-training', 'version' => 1,
                'generated_at' => now()->toIso8601String(), 'metadata' => $metadata, 'turnos' => $shifts];
            $json = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
            $temporary = tempnam($directory, '.mmqp-training-');
            if ($temporary === false || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
                throw new RuntimeException('No se pudo escribir el archivo de entrenamiento.');
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException('No se pudo guardar el archivo de entrenamiento.');
            }
            $temporary = null;
            $this->info('Exportación completada; la base de datos se conserva sin cambios.');
            $this->line('Turnos históricos: '.count($shifts));
            $this->line('Incidencias excluidas: '.$incidents);
            $this->line('SHA256 del dataset: '.$metadata['dataset_sha256']);

            return self::SUCCESS;
        } catch (Throwable $error) {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
            $this->error('Exportación cancelada: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}
