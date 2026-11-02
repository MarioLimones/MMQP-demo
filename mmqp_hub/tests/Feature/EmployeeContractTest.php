<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_clear_contract_dates_and_employee_sees_current_values(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $payload = [
            'nombre' => 'Contrato de prueba',
            'rol' => 'auxiliar',
            'tipo_disponibilidad' => 'completa',
            'fecha_inicio' => '2026-01-10',
            'fecha_fin_contrato' => '2026-12-31',
        ];

        $this->actingAs($admin)->post(route('admin.empleados.guardar'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.empleados'));

        $employee = User::where('nombre', 'Contrato de prueba')->sole();
        $this->assertSame('2026-01-10', $employee->fecha_inicio->toDateString());
        $this->assertSame('2026-12-31', $employee->fecha_fin_contrato->toDateString());
        $this->actingAs($employee)->get(route('profile.datos'))->assertOk()
            ->assertSee('Información contractual')->assertSee('10/01/2026')->assertSee('31/12/2026');

        $payload['fecha_inicio'] = '2026-02-01';
        $payload['fecha_fin_contrato'] = '2027-02-01';
        $this->actingAs($admin)->put(route('admin.empleados.actualizar', $employee), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($employee->fresh())->get(route('profile.datos'))->assertOk()
            ->assertSee('01/02/2026')->assertSee('01/02/2027')->assertDontSee('31/12/2026');

        $payload['fecha_fin_contrato'] = '';
        $this->actingAs($admin)->put(route('admin.empleados.actualizar', $employee), $payload)
            ->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->fecha_fin_contrato);
        $this->actingAs($employee->fresh())->get(route('profile.datos'))->assertOk()
            ->assertSee('Sin fecha de finalización registrada');
    }

    public function test_contract_end_cannot_precede_start_and_invalid_date_does_not_partially_save(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $employee = User::factory()->create(['fecha_inicio' => '2026-01-10', 'fecha_fin_contrato' => '2026-12-31']);

        foreach (['2026-01-09', 'not-a-date'] as $invalidEnd) {
            $this->actingAs($admin)->put(route('admin.empleados.actualizar', $employee), [
                'nombre' => 'Should not be saved', 'rol' => 'auxiliar', 'tipo_disponibilidad' => 'completa',
                'fecha_inicio' => '2026-01-10', 'fecha_fin_contrato' => $invalidEnd,
            ])->assertSessionHasErrors('fecha_fin_contrato');
            $this->assertNotSame('Should not be saved', $employee->fresh()->nombre);
            $this->assertSame('2026-12-31', $employee->fresh()->fecha_fin_contrato->toDateString());
        }
    }

    public function test_legacy_employee_without_contract_dates_has_explicit_empty_states(): void
    {
        $employee = User::factory()->create(['fecha_inicio' => null, 'fecha_fin_contrato' => null]);
        $this->actingAs($employee)->get(route('profile.datos'))->assertOk()
            ->assertSee('Sin fecha de inicio registrada')->assertSee('Sin fecha de finalización registrada');
    }

    public function test_regular_employee_cannot_change_contract_dates(): void
    {
        $employee = User::factory()->create(['es_admin' => false, 'fecha_fin_contrato' => '2026-12-31']);
        $this->actingAs($employee)->put(route('admin.empleados.actualizar', $employee), [
            'nombre' => $employee->nombre, 'rol' => 'auxiliar', 'tipo_disponibilidad' => 'completa',
            'fecha_fin_contrato' => '2027-12-31',
        ])->assertForbidden();
        $this->assertSame('2026-12-31', $employee->fresh()->fecha_fin_contrato->toDateString());
    }
}
