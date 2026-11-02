<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\User;
use App\Services\HistoricalScheduleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalScheduleDeletedRowsTest extends TestCase
{
    use RefreshDatabase;

    private function shift(array $changes = []): array
    {
        return array_replace(['source_id' => 'valid-shift', 'fecha' => '2026-08-25',
            'empleado' => 'Persona histórica', 'empleado_documento' => 'documento-ficticio-1',
            'hora_inicio' => '12:00', 'hora_fin' => '15:30', 'duracion_minutos' => 210,
            'puesto' => 'auxiliar', 'archivo_origen' => 'Registro 2026.PDF', 'paginas_origen' => [1],
            'notas_dia' => ''], $changes);
    }

    private function incident(string $source, array $changes = []): array
    {
        return array_replace(['source_id' => $source, 'fecha' => '2026-08-25',
            'empleado' => 'Persona histórica', 'empleado_documento' => 'documento-ficticio-1',
            'archivo_origen' => 'Registro 2026.PDF', 'paginas_origen' => [1],
            'notas_dia' => 'Borrado 13:01',
            'datos_origen' => ['codigo' => 'borrado_documento', 'texto' => 'Borrado 13:01']], $changes);
    }

    public function test_legacy_deleted_incidents_are_ignored_and_valid_shifts_on_the_same_day_are_retained(): void
    {
        $deleted = $this->incident('deleted-note', ['empleado' => 'Persona borrada',
            'empleado_documento' => 'documento-borrado', 'datos_origen' => ['codigo' => 'sin_pareja']]);
        $actualIncident = $this->incident('incomplete', [
            'notas_dia' => 'Entrada 17:00 sin pareja.', 'datos_origen' => ['codigo' => 'sin_pareja', 'hora' => '17:00']]);
        $summary = app(HistoricalScheduleImporter::class)->import([
            'turnos' => [$this->shift(['notas_dia' => 'Borrado 13:01; Revisar fichaje.; Borrado 11:33'])],
            'incidencias' => [$this->incident('deleted-code', ['notas_dia' => 'Anotación retirada del original.']),
                $deleted, $deleted, $actualIncident],
        ]);

        $this->assertSame(5, $summary['input_rows']);
        $this->assertSame(3, $summary['ignored_deleted_rows']);
        $this->assertSame(0, $summary['duplicates_in_input']);
        $this->assertSame(2, $summary['unique_rows']);
        $this->assertSame(1, $summary['new_shifts']);
        $this->assertSame(1, $summary['new_incidents']);
        $this->assertSame(1, $summary['new_weeks']);
        $schedule = HorarioSemanal::firstOrFail();
        $day = $schedule->plantilla_diseno[1];
        $this->assertCount(1, $day['turnos_requeridos']);
        $this->assertSame(210, $day['turnos_requeridos'][0]['duracion_minutos']);
        $this->assertSame('Revisar fichaje.', $day['turnos_requeridos'][0]['notas_dia']);
        $this->assertSame(['incomplete'], array_column($day['incidencias_importacion'], 'source_id'));
        $this->assertStringNotContainsString('Borrado', $day['notas']);
        $this->assertCount(1, $schedule->empleados_snapshot);
        $this->assertSame(1, $schedule->turnos_totales);
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('fichajes')->count());
    }

    public function test_deleted_only_batches_are_successful_no_ops_and_empty_batches_remain_invalid(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mmqp-deleted-history-');
        try {
            file_put_contents($path, json_encode(['incidencias' => [
                $this->incident('deleted-only', ['fecha' => '2024-02-15',
                    'empleado' => 'Persona borrada', 'empleado_documento' => 'persona-borrada']),
                ['notas_dia' => 'Borrado 00:33'],
            ]], JSON_THROW_ON_ERROR));
            $this->artisan('horarios:importar-historico', ['ruta' => $path])
                ->expectsOutput('Importación completada.')
                ->expectsOutput('Registros borrados ignorados: 2')
                ->expectsOutput('Duplicados idénticos en el JSON: 0')
                ->expectsOutput('Semanas nuevas: 0')
                ->assertSuccessful();
            $this->artisan('horarios:importar-historico', ['ruta' => $path, '--dry-run' => true])
                ->expectsOutput('Registros borrados ignorados: 2')->assertSuccessful();
            file_put_contents($path, json_encode(['turnos' => [], 'incidencias' => []], JSON_THROW_ON_ERROR));
            $this->artisan('horarios:importar-historico', ['ruta' => $path])->assertFailed();
        } finally {
            unlink($path);
        }

        $this->assertSame(0, HorarioSemanal::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('fichajes')->count());
    }

    public function test_regenerated_batch_matches_legacy_shift_annotations_and_ignores_deleted_stored_incidents(): void
    {
        $service = app(HistoricalScheduleImporter::class);
        $service->import([$this->shift()]);
        $schedule = HorarioSemanal::firstOrFail();
        $layout = $schedule->plantilla_diseno;
        $layout[1]['turnos_requeridos'][0]['notas_dia'] = 'Borrado 13:01; Borrado 11:33';
        $layout[1]['notas'] = 'Borrado 13:01; Borrado 11:33';
        $layout[1]['incidencias_importacion'] = [
            $this->incident('legacy-deleted', ['notas_dia' => 'Borrado 13:01']),
            $this->incident('legacy-deleted', ['notas_dia' => 'Borrado 11:33']),
            ['notas_dia' => 'Borrado 00:33'],
        ];
        DB::table('horarios_semanales')->where('id', $schedule->id)
            ->update(['plantilla_diseno' => json_encode($layout, JSON_THROW_ON_ERROR)]);
        $before = $schedule->fresh()->getRawOriginal();

        $summary = $service->import(['turnos' => [$this->shift()], 'incidencias' => []]);
        $this->assertSame(1, $summary['already_imported']);
        $this->assertSame(0, $summary['new_shifts']);
        $this->assertSame(0, $summary['new_weeks']);
        $this->assertSame($before, $schedule->fresh()->getRawOriginal());
        $this->assertSame(1, HorarioSemanal::count());
    }

    public function test_deleted_annotations_are_removed_consistently_on_retries_without_losing_other_notes(): void
    {
        $service = app(HistoricalScheduleImporter::class);
        $service->import([$this->shift(['notas_dia' => 'Borrador revisado; Borrado 12:04; Revisar fichaje.'])]);
        $summary = $service->import([$this->shift(['notas_dia' => 'Borrador revisado; Revisar fichaje.'])]);
        $this->assertSame(1, $summary['already_imported']);
        $this->assertSame(0, $summary['new_weeks']);
        $schedule = HorarioSemanal::firstOrFail();
        $this->assertSame('Borrador revisado; Revisar fichaje.', $schedule->plantilla_diseno[1]['turnos_requeridos'][0]['notas_dia']);
    }

    public function test_a_genuine_incident_mentioning_a_deleted_note_is_not_discarded(): void
    {
        $summary = app(HistoricalScheduleImporter::class)->import(['incidencias' => [
            $this->incident('actual-incident', ['notas_dia' => 'Entrada sin pareja; Borrado 12:00 en otra anotación.',
                'datos_origen' => ['codigo' => 'sin_pareja']]),
        ]]);

        $this->assertSame(0, $summary['ignored_deleted_rows']);
        $this->assertSame(1, $summary['new_incidents']);
        $this->assertSame(1, $summary['new_weeks']);
        $this->assertSame(0, $summary['new_shifts']);
    }
}
