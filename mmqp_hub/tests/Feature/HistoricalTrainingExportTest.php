<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalTrainingExportTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function destination(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mmqp-training-test-');
        unlink($file);
        $path = $file.'.json';
        $this->files[] = $path;

        return $path;
    }

    private function schedule(string $state = 'historico', array $shifts = []): HorarioSemanal
    {
        return HorarioSemanal::create(['semana_id' => '2023-W01', 'fecha_inicio' => '2023-01-02',
            'fecha_fin' => '2023-01-08', 'estado' => $state, 'vigente' => $state === 'published',
            'plantilla_diseno' => [['dia' => 'lunes', 'fecha' => '2023-01-02',
                'turnos_requeridos' => $shifts, 'incidencias_importacion' => [['notas_dia' => 'Nota privada']]]],
            'resultados' => [], 'empleados_snapshot' => []]);
    }

    private function shift(array $changes = []): array
    {
        return array_replace(['source_id' => 'source-a', 'fecha' => '2023-01-02',
            'rol_necesario' => 'repartidor', 'hora_inicio' => '20:00', 'hora_fin' => '02:30',
            'duracion_minutos' => 390, 'empleado_id' => 'hist_persona_uno',
            'empleado_nombre' => 'Nombre privado', 'empleado_documento' => 'documento-privado',
            'notas_dia' => 'Otra nota privada', 'fichaje_entrada' => 'fichaje privado',
            'archivo_origen' => 'Registro 2023.pdf', 'archivo_sha256' => str_repeat('a', 64),
            'paginas_origen' => [2]], $changes);
    }

    public function test_exports_only_history_without_personal_fields_or_database_writes(): void
    {
        $this->schedule('historico', [$this->shift(), $this->shift(['source_id' => 'source-b',
            'rol_necesario' => 'sin_puesto', 'hora_fin' => '20:00', 'duracion_minutos' => 0])]);
        $this->schedule('borrador', [$this->shift(['source_id' => 'draft-secret'])]);
        $this->schedule('published', [$this->shift(['source_id' => 'published-secret'])]);
        $before = DB::table('horarios_semanales')->orderBy('id')->get()->toJson();
        $path = $this->destination();
        $this->artisan('horarios:exportar-entrenamiento', ['salida' => $path])
            ->expectsOutput('Turnos históricos: 2')->expectsOutput('Incidencias excluidas: 1')->assertSuccessful();

        $raw = file_get_contents($path);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('mmqp.historical-training', $data['format']);
        $this->assertSame(1, $data['version']);
        $this->assertCount(2, $data['turnos']);
        $this->assertSame(1, $data['metadata']['historical_schedules']);
        $this->assertSame(['2023' => 2], $data['metadata']['by_year']);
        $this->assertSame(0, $data['turnos'][1]['duracion_minutos']);
        $this->assertSame('sin_puesto', $data['turnos'][1]['puesto']);
        $this->assertSame($data['turnos'][0]['empleado_id'], $data['turnos'][1]['empleado_id']);
        $this->assertStringStartsWith('anon_', $data['turnos'][0]['empleado_id']);
        foreach (['Nombre privado', 'documento-privado', 'Nota privada', 'fichaje privado',
            'draft-secret', 'published-secret', 'hist_persona_uno'] as $private) {
            $this->assertStringNotContainsString($private, $raw);
        }
        $this->assertSame($before, DB::table('horarios_semanales')->orderBy('id')->get()->toJson());
    }

    public function test_identities_are_not_joined_by_name_and_dataset_hash_is_reproducible(): void
    {
        $this->schedule('historico', [$this->shift(), $this->shift(['source_id' => 'source-b', 'empleado_id' => 'hist_persona_dos']),
            $this->shift(['source_id' => 'source-c', 'empleado_id' => null])]);
        $first = $this->destination();
        $second = $this->destination();
        foreach ([$first, $second] as $path) {
            $this->artisan('horarios:exportar-entrenamiento', ['salida' => $path])->assertSuccessful();
        }
        $a = json_decode(file_get_contents($first), true, 512, JSON_THROW_ON_ERROR);
        $b = json_decode(file_get_contents($second), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotSame($a['turnos'][0]['empleado_id'], $a['turnos'][1]['empleado_id']);
        $this->assertNull($a['turnos'][2]['empleado_id']);
        $this->assertSame($a['metadata']['dataset_sha256'], $b['metadata']['dataset_sha256']);
        $this->assertSame($a['turnos'], $b['turnos']);
    }

    public function test_existing_destination_requires_explicit_force_and_empty_history_does_not_write(): void
    {
        $path = $this->destination();
        $this->artisan('horarios:exportar-entrenamiento', ['salida' => $path])->assertFailed();
        $this->assertFileDoesNotExist($path);
        $this->schedule('historico', [$this->shift()]);
        file_put_contents($path, 'existing');
        $this->artisan('horarios:exportar-entrenamiento', ['salida' => $path])->assertFailed();
        $this->assertSame('existing', file_get_contents($path));
        $this->artisan('horarios:exportar-entrenamiento', ['salida' => $path, '--force' => true])->assertSuccessful();
        $this->assertJson(file_get_contents($path));
    }
}
