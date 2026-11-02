<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HistoricalScheduleImporter;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Throwable;

class ImportarHorariosHistoricos extends Command
{
    protected $signature = 'horarios:importar-historico {ruta : JSON con fechas completas y source_id}
        {--dry-run : Validar y contar sin escribir horarios}
        {--generado-por= : ID de un usuario existente; por defecto no se atribuye a nadie}';

    protected $description = 'Importa registros documentales como históricos independientes sin publicar ni modificar horarios existentes.';

    public function handle(HistoricalScheduleImporter $importer): int
    {
        try {
            $path = $this->argument('ruta');
            if (! is_file($path) || ! is_readable($path)) {
                throw new \InvalidArgumentException('El archivo JSON no existe o no se puede leer.');
            }
            $input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $generatedBy = $this->option('generado-por');
            if ($generatedBy !== null && (! ctype_digit((string) $generatedBy) || (int) $generatedBy < 1
                || ! User::whereKey((int) $generatedBy)->exists())) {
                throw new \InvalidArgumentException('--generado-por debe identificar a un usuario existente.');
            }

            $summary = $importer->import($input, (bool) $this->option('dry-run'), $generatedBy === null ? null : (int) $generatedBy);
            $this->info($summary['dry_run'] ? 'Validación completada; no se ha escrito ningún horario.' : 'Importación completada.');
            foreach (['input_rows' => 'Filas recibidas', 'ignored_deleted_rows' => 'Registros borrados ignorados', 'unique_rows' => 'Registros únicos',
                'duplicates_in_input' => 'Duplicados idénticos en el JSON', 'already_imported' => 'Registros ya importados',
                'new_shifts' => 'Turnos nuevos', 'new_incidents' => 'Incidencias nuevas', 'new_weeks' => 'Semanas nuevas'] as $key => $label) {
                $this->line($label.': '.$summary[$key]);
            }

            return self::SUCCESS;
        } catch (QueryException $error) {
            $this->error('Importación cancelada: la base de datos rechazó el lote y se han revertido todas sus inserciones.');

            return self::FAILURE;
        } catch (Throwable $error) {
            $this->error('Importación cancelada: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}
