<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\SolicitudAusencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']));
    }

    private function payload(): array
    {
        $horario = array_fill_keys(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'], []);
        $horario['lunes'] = [['inicio' => '12:00', 'fin' => '16:30'], ['inicio' => '19:00', 'fin' => '00:30']];

        return ['semana' => '2026-W43', 'horario' => $horario];
    }

    private function employee(array $changes = []): User
    {
        return User::factory()->create($changes + ['activo' => true, 'en_baja' => false,
            'rol' => 'auxiliar', 'horas_mensuales' => 80, 'tipo_disponibilidad' => 'completa']);
    }

    public function test_real_python_uses_demo_contracts_without_operational_training_data_or_writing_schedules(): void
    {
        $employee = $this->employee(['horas_mensuales' => 16]); // 4 h/semana.
        $this->employee(['activo' => false, 'rol' => 'repartidor']);
        $this->employee(['en_baja' => true, 'rol' => 'encargado']);
        $files = [base_path('../algoritmo/datos_entrenamiento.json'), base_path('../algoritmo/modelo_preferencias.joblib'),
            base_path('../algoritmo/modelo_cobertura.json'), base_path('../algoritmo/informe_entrenamiento.json')];
        // The public demo deliberately contains no operational training files or derived models.
        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }

        $result = $this->postJson('/api/generar-plantilla-ia', $this->payload())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('resumen.0.id', $employee->id)
            ->assertJsonPath('modelo_historico.estado', 'no_disponible')
            ->assertJsonPath('resumen.0.minutos_objetivo', 240)->assertJsonPath('resumen.0.minutos_generados', 240)->json();
        $this->assertArrayNotHasKey('anios', $result['modelo_historico']);
        $this->assertArrayNotHasKey('turnos_utilizados', $result['modelo_historico']);
        $this->assertSame(['auxiliar'], collect($result['plantilla'])->flatten(1)->pluck('rol')->unique()->values()->all());
        $employee->update(['horas_mensuales' => 32]);
        $this->postJson('/api/generar-plantilla-ia', $this->payload())->assertOk()
            ->assertJsonPath('resumen.0.minutos_objetivo', 480)->assertJsonPath('resumen.0.minutos_generados', 480);
        $this->assertSame(0, HorarioSemanal::count());
        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    public function test_approved_absence_and_recurrent_blocks_are_sent_to_python(): void
    {
        $employee = $this->employee(['dias_bloqueados_recurrentes' => ['miercoles']]);
        SolicitudAusencia::create(['user_id' => $employee->id, 'fecha' => '2026-10-19',
            'fecha_fin' => '2026-10-19', 'tipo' => 'vacaciones', 'estado' => 'aprobada']);
        $payload = $this->payload();
        $payload['horario']['miercoles'] = $payload['horario']['lunes'];
        $result = $this->postJson('/api/generar-plantilla-ia', $payload)->assertOk()
            ->assertJsonPath('resumen.0.minutos_generados', 0)->json();
        $this->assertSame([], $result['plantilla']['lunes']);
        $this->assertSame([], $result['plantilla']['miercoles']);
        $this->assertNotEmpty($result['avisos']);
    }

    public function test_minor_is_reserved_until_22_while_adult_shifts_stay_unassigned(): void
    {
        $minor = $this->employee(['es_menor' => true, 'horas_mensuales' => 12]);
        $this->employee(['es_menor' => false, 'horas_mensuales' => 14]);
        $payload = $this->payload();
        $payload['horario']['lunes'] = [['inicio' => '19:00', 'fin' => '00:30']];

        $result = $this->postJson('/api/generar-plantilla-ia', $payload)
            ->assertOk()->assertJsonPath('success', true)->json();
        $shifts = collect($result['plantilla'])->flatten(1);
        $reserved = $shifts->where('empleado_id', $minor->id)->values();
        $unassigned = $shifts->filter(fn ($shift) => empty($shift['empleado_id']))->values();

        $this->assertCount(1, $reserved);
        $this->assertSame('19:00', $reserved[0]['hora_inicio']);
        $this->assertSame('22:00', $reserved[0]['hora_fin']);
        $this->assertNotEmpty($unassigned);
        $this->assertSame([$minor->id], $shifts->pluck('empleado_id')->filter()->unique()->values()->all());
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_two_minors_are_reserved_individually_and_approved_absences_stay_blocked(): void
    {
        $first = $this->employee(['es_menor' => true, 'horas_mensuales' => 48]);
        $second = $this->employee(['es_menor' => true, 'horas_mensuales' => 48]);
        SolicitudAusencia::create(['user_id' => $first->id, 'fecha' => '2026-10-20',
            'fecha_fin' => '2026-10-20', 'tipo' => 'vacaciones', 'estado' => 'aprobada']);
        $payload = $this->payload();
        $payload['horario'] = array_fill_keys(array_keys($payload['horario']), [['inicio' => '19:00', 'fin' => '00:30']]);

        $result = $this->postJson('/api/generar-plantilla-ia', $payload)
            ->assertOk()->assertJsonPath('success', true)->json();
        $shifts = collect($result['plantilla'])->flatten(1);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $shifts->pluck('empleado_id')->unique()->all());
        $this->assertFalse(collect($result['plantilla']['martes'])->contains('empleado_id', $first->id));
        foreach ($shifts as $shift) {
            $this->assertGreaterThanOrEqual('06:00', $shift['hora_inicio']);
            $this->assertLessThanOrEqual('22:00', $shift['hora_fin']);
            $this->assertGreaterThan($shift['hora_inicio'], $shift['hora_fin']);
        }
        foreach ($result['resumen'] as $employee) {
            $this->assertSame(720, $employee['minutos_generados']);
        }
    }

    public function test_previous_synthetic_published_week_guides_demo_fallback_without_copying_assignments(): void
    {
        $this->employee(['horas_mensuales' => 16]);
        $layout = [];
        foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $index => $day) {
            $layout[] = ['dia' => $day, 'fecha' => '2026-10-'.(12 + $index),
                'turnos_requeridos' => $day === 'martes' ? [[
                    'turno_id' => 'old-assignment', 'rol_necesario' => 'auxiliar',
                    'hora_inicio' => '12:00', 'hora_fin' => '16:00', 'empleado_id' => 'old-worker',
                ]] : []];
        }
        HorarioSemanal::create(['semana_id' => '2026-W42', 'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-18',
            'estado' => 'published', 'vigente' => true, 'roles_publicados' => ['auxiliar'],
            'plantilla_diseno' => $layout, 'resultados' => [], 'empleados_snapshot' => []]);
        $payload = $this->payload();
        $payload['horario'] = array_fill_keys(array_column($layout, 'dia'), [['inicio' => '12:00', 'fin' => '16:00']]);
        $result = $this->postJson('/api/generar-plantilla-ia', $payload)->assertOk()
            ->assertJsonPath('modelo_historico.estado', 'no_disponible')->json();
        $this->assertSame(['martes'], array_keys(array_filter($result['plantilla'])));
        $this->assertSame([['rol' => 'auxiliar', 'hora_inicio' => '12:00', 'hora_fin' => '16:00']], $result['plantilla']['martes']);
        $this->assertContains(['dia' => 'martes', 'rol' => 'auxiliar', 'fecha' => '2026-10-13',
            'tipo' => 'semana_anterior', 'peso' => 64], $result['modelo_historico']['referencias']);
        $this->assertSame(1, HorarioSemanal::count());
    }

    public function test_pending_absence_does_not_remove_available_contract_hours(): void
    {
        $employee = $this->employee(['horas_mensuales' => 16, 'tipo_disponibilidad' => 'personalizada',
            'dias_disponibles' => ['lunes' => ['mañana']]]);
        SolicitudAusencia::create(['user_id' => $employee->id, 'fecha' => '2026-10-19',
            'tipo' => 'vacaciones', 'estado' => 'pendiente']);
        $result = $this->postJson('/api/generar-plantilla-ia', $this->payload())->assertOk()
            ->assertJsonPath('resumen.0.minutos_generados', 240)->json();
        foreach ($result['plantilla']['lunes'] as $shift) {
            $this->assertLessThan('14:00', $shift['hora_inicio']);
        }
    }

    public function test_overlapping_ranges_are_rejected_with_an_actionable_error(): void
    {
        $payload = $this->payload();
        $payload['horario']['lunes'][1] = ['inicio' => '15:00', 'fin' => '18:00'];
        $this->postJson('/api/generar-plantilla-ia', $payload)->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonPath('message', 'Las franjas de un mismo día no pueden solaparse.');
    }

    public function test_invalid_week_and_missing_working_hours_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['semana'] = '2021-W53';
        $this->postJson('/api/generar-plantilla-ia', $payload)->assertUnprocessable()->assertJsonValidationErrors('semana');
        unset($payload['horario']);
        $this->postJson('/api/generar-plantilla-ia', $payload)->assertUnprocessable()->assertJsonValidationErrors('horario');
    }

    public function test_employee_cannot_generate_templates(): void
    {
        $this->actingAs($this->employee(['es_admin' => false]))
            ->postJson('/api/generar-plantilla-ia', $this->payload())->assertForbidden();
    }
}
