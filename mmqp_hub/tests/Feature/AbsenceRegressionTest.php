<?php

namespace Tests\Feature;

use App\Models\SolicitudAusencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsenceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_vacation_is_visible_to_employee(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $employee = User::factory()->create();
        $this->actingAs($admin)->post('/admin/vacaciones', [
            'user_id' => $employee->id, 'fecha' => '2026-11-02',
            'fecha_fin' => '2026-11-04', 'tipo' => 'vacaciones',
            'request_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->actingAs($employee->fresh())->get('/perfil/vacaciones')
            ->assertOk()->assertViewHas('vacaciones_json', json_encode(['2026-11-02', '2026-11-03', '2026-11-04']));
    }

    public function test_rejecting_an_approved_range_removes_its_effect(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $employee = User::factory()->create();
        $this->actingAs($admin)->post('/admin/vacaciones', [
            'user_id' => $employee->id, 'fecha' => '2026-11-02',
            'fecha_fin' => '2026-11-04', 'tipo' => 'dia_libre',
            'request_key' => (string) Str::uuid(),
        ]);
        $absence = SolicitudAusencia::first();
        $this->put('/admin/solicitudes/'.$absence->id, ['estado' => 'rechazada', 'version' => $absence->version ?? 1]);
        $this->assertSame([], $employee->fresh()->fechas_libres_solicitadas ?? []);
    }

    public function test_deleting_one_overlap_keeps_the_other_absence(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $employee = User::factory()->create();
        foreach ([['2026-11-02', '2026-11-04'], ['2026-11-03', '2026-11-05']] as [$start, $end]) {
            $this->actingAs($admin)->post('/admin/vacaciones', [
                'user_id' => $employee->id, 'fecha' => $start, 'fecha_fin' => $end,
                'tipo' => 'dia_libre', 'request_key' => (string) Str::uuid(),
            ]);
        }
        $absence = SolicitudAusencia::first();
        $this->delete('/admin/vacaciones/'.$absence->id, ['version' => $absence->version ?? 1]);
        $this->assertSame(['2026-11-03', '2026-11-04', '2026-11-05'], $employee->fresh()->fechas_libres_solicitadas);
    }
}
