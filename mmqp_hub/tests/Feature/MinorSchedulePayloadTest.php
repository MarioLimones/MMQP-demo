<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScheduleDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MinorSchedulePayloadTest extends TestCase
{
    use RefreshDatabase;

    private string $isolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedStorage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mmqp-minor-schedule-'.Str::uuid();
        File::makeDirectory($this->isolatedStorage.DIRECTORY_SEPARATOR.'logs', 0755, true);
        $this->app->useStoragePath($this->isolatedStorage);
        config(['logging.default' => 'null']);
        $this->actingAs(User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']));
    }

    protected function tearDown(): void
    {
        if (isset($this->isolatedStorage)) {
            $resolved = realpath($this->isolatedStorage);
            $temporaryRoot = realpath(sys_get_temp_dir());
            if ($resolved !== false && dirname($resolved) === $temporaryRoot
                && str_starts_with(basename($resolved), 'mmqp-minor-schedule-')) {
                File::deleteDirectory($resolved);
            }
        }
        parent::tearDown();
    }

    public function test_final_generator_receives_current_minor_status_from_database_and_preserves_the_reservation(): void
    {
        $minor = User::factory()->create(['es_menor' => true, 'activo' => true, 'en_baja' => false,
            'rol' => 'auxiliar', 'horas_mensuales' => 12]);
        $adult = User::factory()->create(['es_menor' => false, 'activo' => true, 'en_baja' => false,
            'rol' => 'auxiliar', 'horas_mensuales' => 12]);
        $developer = User::factory()->create(['rol' => 'admin', 'es_admin' => true,
            'activo' => true, 'en_baja' => false, 'horas_mensuales' => 160]);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        $this->assertNotContains($developer->id, array_column($draft->empleados_snapshot, 'id'));
        Http::fake(['127.0.0.1:5000/*' => Http::response(['success' => true, 'resultados' => [[
            'turno_id' => 'reserved-minor', 'empleado_id' => $minor->id, 'nombre' => $minor->nombre,
            'hora_inicio' => '19:00', 'hora_fin' => '22:00', 'duracion_horas' => 3,
        ]]])]);

        $this->postJson('/api/generar-horario', [
            'draft_id' => $draft->id, 'version' => $draft->version, 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25'],
            // Client-supplied employee metadata must never weaken the database flag.
            'empleados' => [['id' => $minor->id, 'es_menor' => false], ['id' => $adult->id, 'es_menor' => true]],
            'ajustes_manuales' => [$minor->id => 3, $adult->id => 3],
            'plantilla_diseno' => [['dia' => 'lunes', 'fecha' => '2026-10-19', 'turnos_requeridos' => [[
                'turno_id' => 'reserved-minor', 'rol_necesario' => 'auxiliar', 'hora_inicio' => '19:00',
                'hora_fin' => '22:00', 'duracion_horas' => 3, 'empleado_id' => $minor->id, 'reserva_menor' => true,
            ]]]],
        ])->assertOk()->assertJsonPath('success', true);

        Http::assertSent(function ($request) use ($minor, $adult, $developer) {
            $employees = collect($request['empleados'])->keyBy('id');
            return !$employees->has($developer->id)
                && $employees[$minor->id]['es_menor'] === true
                && $employees[$adult->id]['es_menor'] === false
                && $request['plantilla_diseno'][0]['turnos_requeridos'][0]['empleado_id'] === $minor->id
                && $request['plantilla_diseno'][0]['turnos_requeridos'][0]['reserva_menor'] === true;
        });
        Http::assertSentCount(1);
        $this->assertSame($minor->id, $draft->fresh()->plantilla_diseno[0]['turnos_requeridos'][0]['empleado_id']);
        $this->assertTrue($draft->fresh()->plantilla_diseno[0]['turnos_requeridos'][0]['reserva_menor']);
        $this->assertSame($minor->id, $draft->fresh()->resultados[0]['empleado_id']);
    }
}
