<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScheduleDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function generationPayload(): array
    {
        $draft = app(ScheduleDrafts::class)->open('2026-W43');

        return ['draft_id' => $draft->id, 'version' => $draft->version, 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25'],
            'plantilla_diseno' => [['dia' => 'lunes', 'fecha' => '2026-10-19', 'turnos_requeridos' => [[
                'turno_id' => 'manual-hours', 'rol_necesario' => 'auxiliar', 'hora_inicio' => '12:00',
                'hora_fin' => '16:00', 'duracion_horas' => 4,
            ]]]]];
    }

    public function test_missing_or_empty_weekly_hours_do_not_fall_back_to_contracts_or_call_the_solver(): void
    {
        $this->actingAs(User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']));
        $worker = User::factory()->create(['activo' => true, 'rol' => 'auxiliar', 'horas_mensuales' => 160, 'en_baja' => false]);
        Http::fake();
        $payload = $this->generationPayload();
        foreach ([[], [$worker->id => null], [$worker->id => '']] as $hours) {
            $this->postJson('/api/generar-horario', $payload + ['ajustes_manuales' => $hours])->assertOk()
                ->assertJsonPath('requires_hour_input', true)->assertJsonPath('disponibles.auxiliar', 0);
        }
        Http::assertNothingSent();
        $this->assertDatabaseHas('horarios_semanales', ['id' => $payload['draft_id'], 'version' => 1, 'estado' => 'borrador']);
    }

    public function test_only_explicit_positive_weekly_hours_reach_the_solver_and_zero_is_preserved(): void
    {
        $this->actingAs(User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']));
        $worker = User::factory()->create(['activo' => true, 'rol' => 'auxiliar', 'horas_mensuales' => 160, 'en_baja' => false]);
        $zero = User::factory()->create(['activo' => true, 'rol' => 'auxiliar', 'horas_mensuales' => 160, 'en_baja' => false]);
        Http::fake(['127.0.0.1:5000/*' => Http::response(['success' => true, 'resultados' => []])]);
        $payload = $this->generationPayload();
        $this->postJson('/api/generar-horario', $payload + ['ajustes_manuales' => [$worker->id => 4, $zero->id => 0]])
            ->assertOk()->assertJsonPath('success', true);
        Http::assertSent(fn ($request) => count($request['empleados']) === 1
            && $request['empleados'][0]['id'] === $worker->id && $request['empleados'][0]['horas_semanales_override'] == 4);
    }

    public function test_open_refreshes_employee_status_without_rewriting_the_saved_snapshot(): void
    {
        $admin = User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']);
        $employee = User::factory()->create(['activo' => false, 'rol' => 'auxiliar', 'en_baja' => false]);
        $this->actingAs($admin);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        $snapshot = [
            ['id' => $employee->id, 'nombre' => 'Nombre conservado', 'rol' => 'auxiliar', 'horas_mensuales' => 80],
            ['id' => 999999, 'nombre' => 'Empleado antiguo', 'rol' => 'auxiliar'],
        ];
        $draft->update(['empleados_snapshot' => $snapshot]);
        $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => '2026-W43'])
            ->assertOk()->assertJsonPath('draft.empleados_snapshot.0.activo', false)
            ->assertJsonPath('draft.empleados_snapshot.1.activo', false)
            ->assertJsonPath('draft.empleados_snapshot.0.nombre', 'Nombre conservado');
        $this->assertSame($snapshot, $draft->fresh()->empleados_snapshot);
    }

    public function test_confirmation_returns_the_generator_roster_and_balanced_retry_removes_the_deficit(): void
    {
        $admin = User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']);
        $active = User::factory()->count(3)->create(['activo' => true, 'rol' => 'auxiliar', 'en_baja' => false, 'horas_mensuales' => 160]);
        $inactive = User::factory()->create(['activo' => false, 'rol' => 'auxiliar', 'en_baja' => false, 'horas_mensuales' => 80]);
        $onLeave = User::factory()->create(['activo' => true, 'rol' => 'auxiliar', 'en_baja' => true, 'horas_mensuales' => 160]);
        $this->actingAs($admin);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        $layout = [];
        foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'] as $i => $day) {
            $layout[] = ['dia' => $day, 'fecha' => '2026-10-'.(19 + $i), 'turnos_requeridos' => [[
                'turno_id' => 'aux-'.$i, 'rol_necesario' => 'auxiliar', 'hora_inicio' => '09:00',
                'hora_fin' => '17:00', 'duracion_horas' => 8,
            ]]];
        }
        $payload = ['draft_id' => $draft->id, 'version' => $draft->version, 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25'],
            'plantilla_diseno' => $layout,
            'ajustes_manuales' => $active->pluck('id')->push($inactive->id, $onLeave->id)->mapWithKeys(fn ($id) => [$id => 12])->all()];
        Http::fake(function ($request) {
            $roster = collect($request['empleados'])->where('rol', 'auxiliar');
            $available = $roster->sum('horas_semanales_override');
            return Http::response(abs($available - 48) < 0.001 ? ['success' => true, 'resultados' => []] : [
                'requires_confirmation' => true, 'deficit' => ['auxiliar' => 48 - $available],
                'requeridas' => ['auxiliar' => 48], 'disponibles' => ['auxiliar' => $available],
            ]);
        });
        $confirmation = $this->postJson('/api/generar-horario', $payload)->assertOk()
            ->assertJsonPath('requires_confirmation', true)->assertJsonPath('deficit.auxiliar', 12)->json();
        $ids = collect($confirmation['empleados_disponibles'])->where('rol', 'auxiliar')->pluck('id')->all();
        $this->assertEqualsCanonicalizing($active->pluck('id')->all(), $ids);
        foreach ($ids as $id) $payload['ajustes_manuales'][$id] = 16;
        $payload['request_id'] = (string) Str::uuid();
        $this->postJson('/api/generar-horario', $payload)->assertOk()->assertJsonPath('success', true)
            ->assertJsonMissingPath('requires_confirmation');
        $this->assertSame('borrador', $draft->fresh()->estado);
        $this->assertFalse($draft->fresh()->vigente);
        $this->assertSame(2, $draft->fresh()->version);
        Http::assertSentCount(2);
    }
}
