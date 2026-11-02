<?php

namespace Tests\Feature;

use App\Models\Fichaje;
use App\Models\HorarioSemanal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHoursIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_omits_hour_control_and_preserves_time_and_schedule_history(): void
    {
        $admin = User::factory()->create(['es_admin' => true, 'activo' => true]);
        $employee = User::factory()->create(['activo' => true, 'horas_mensuales' => 80]);
        $clock = Fichaje::create([
            'user_id' => $employee->id, 'entrada' => '2026-10-01 09:00:00',
            'salida' => '2026-10-01 13:00:00', 'horas_calculadas' => 4,
        ]);
        $schedule = HorarioSemanal::create([
            'semana_id' => '2026-W40', 'fecha_inicio' => '2026-09-28', 'fecha_fin' => '2026-10-04',
            'plantilla_diseno' => [], 'resultados' => [['empleado_id' => $employee->id, 'turno_id' => 'L1']],
            'empleados_snapshot' => [], 'turnos_cubiertos' => 1, 'turnos_totales' => 1,
            'generado_por' => $admin->id, 'estado' => 'published', 'vigente' => true,
        ]);
        $beforeClock = $clock->fresh()->getRawOriginal();
        $beforeSchedule = $schedule->fresh()->getRawOriginal();

        $this->actingAs($admin)->get(route('admin.empleados'))
            ->assertOk()->assertDontSee('Control de Horas')->assertViewMissing('horasMensuales')
            ->assertSee($employee->nombre);

        $this->assertSame($beforeClock, $clock->fresh()->getRawOriginal());
        $this->assertSame($beforeSchedule, $schedule->fresh()->getRawOriginal());
        $this->assertSame(80.0, (float) $employee->fresh()->horas_mensuales);
        $this->get(route('admin.empleados.editar', $employee->id))->assertOk();
        $this->get('/admin/registro-jornada')->assertOk();
    }
}
