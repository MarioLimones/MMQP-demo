<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\SolicitudAusencia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TechnicalAdminExclusionTest extends TestCase
{
    use RefreshDatabase;

    private User $technical;
    private User $employee;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->technical = User::factory()->create([
            'nombre' => 'Cuenta técnica', 'rol' => 'admin', 'es_admin' => true,
            'activo' => true, 'dni' => '99000000A',
        ]);
        $this->employee = User::factory()->create([
            'nombre' => 'Personal operativo', 'rol' => 'auxiliar', 'es_admin' => false, 'activo' => true,
        ]);
        $this->manager = User::factory()->create([
            'nombre' => 'Encargada con permisos', 'rol' => 'encargado', 'es_admin' => true, 'activo' => true,
        ]);
        $this->actingAs($this->technical);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_master_pagination_and_active_staff_exclude_only_the_technical_role(): void
    {
        $response = $this->get('/admin/empleados')->assertOk();
        $this->assertSame(2, $response->viewData('empleados')->total());
        $this->assertEqualsCanonicalizing([$this->employee->id, $this->manager->id], $response->viewData('empleados')->modelKeys());
        $response->assertViewMissing('horasMensuales')->assertDontSee('Control de Horas');
        $this->assertNotContains($this->technical->id, $response->viewData('empleados')->modelKeys());
        $this->get('/admin/empleados?estado=todos&rol=admin')->assertOk()
            ->assertViewHas('empleados', fn ($people) => $people->total() === 0);
        $this->get('/admin/empleados/activos')->assertOk()
            ->assertViewHas('agrupados', fn ($groups) => !$groups->has('admin') && $groups->has('encargado'));
        $this->assertTrue(User::findOrFail($this->technical->id)->es_admin);
        $this->get('/dashboard')->assertOk();
    }

    public function test_staff_selectors_and_scheduling_configuration_do_not_contain_the_technical_account(): void
    {
        foreach (['/admin/horarios' => 'empleados', '/admin/vacaciones' => 'empleados',
            '/admin/registro-jornada' => 'empleados', '/admin/arqueo' => 'empleadosList'] as $url => $variable) {
            $this->get($url)->assertOk()->assertViewHas($variable, function ($people) {
                return !$people->contains('id', $this->technical->id)
                    && $people->contains('id', $this->employee->id)
                    && $people->contains('id', $this->manager->id);
            });
        }
        $this->get('/admin/fichajes')->assertOk()->assertViewHas('empleadosDict', fn ($people) =>
            !isset($people[$this->technical->id]) && isset($people[$this->manager->id]));
        $draft = $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => '2026-W43'])
            ->assertOk()
            ->assertJsonCount(2, 'draft.empleados_snapshot');
        $this->assertNotContains($this->technical->id, array_column($draft->json('draft.empleados_snapshot'), 'id'));
    }

    public function test_technical_profile_has_account_settings_without_an_employment_contract_or_personal_absences(): void
    {
        $this->get('/perfil/datos')->assertOk()->assertSeeText('Información de la cuenta')->assertDontSeeText('h / mes');
        $this->get(route('profile.edit'))->assertOk()->assertDontSee('href="'.route('profile.vacaciones').'"', false)
            ->assertDontSee('href="'.route('profile.disponibilidad').'"', false);
        $this->get('/perfil/vacaciones')->assertForbidden();
        $this->get('/perfil/disponibilidad')->assertForbidden();
        $this->actingAs($this->manager)->get('/perfil/datos')->assertOk()->assertSeeText('h / mes');
        $this->get('/perfil/vacaciones')->assertOk();
    }

    public function test_technical_account_cannot_be_created_or_modified_through_employment_actions(): void
    {
        $payload = ['nombre' => 'Nueva cuenta', 'rol' => 'admin', 'tipo_disponibilidad' => 'completa'];
        $this->get('/admin/empleados/crear')->assertOk()->assertDontSee('value="admin"', false);
        $this->postJson('/admin/empleados/crear', $payload)->assertUnprocessable()->assertJsonValidationErrors('rol');
        $this->putJson('/admin/empleados/'.$this->employee->id.'/editar', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('rol');
        $this->get('/admin/empleados/'.$this->technical->id.'/editar')->assertNotFound();
        $this->post('/admin/empleados/'.$this->technical->id.'/baja')->assertNotFound();
        $this->post('/admin/empleados/'.$this->technical->id.'/activo')->assertNotFound();
        $this->actingAs($this->manager)->delete('/admin/empleados/'.$this->technical->id)->assertNotFound();
        $this->assertTrue($this->technical->fresh()->activo);
        $this->assertFalse($this->technical->fresh()->en_baja);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_technical_account_cannot_receive_a_clocking_or_absence_even_with_an_explicit_id(): void
    {
        $this->postJson('/admin/fichajes', ['user_id' => $this->technical->id,
            'fecha' => '2026-10-30', 'entrada' => '10:00'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson('/api/kiosko/fichar', ['user_id' => $this->technical->id, 'pin' => '99000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson('/admin/vacaciones', ['user_id' => $this->technical->id,
            'tipo' => 'vacaciones', 'fecha' => '2026-11-02', 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('fichajes', 0);
        $this->assertDatabaseCount('solicitud_ausencias', 0);
    }

    public function test_old_technical_absences_do_not_appear_in_requests_or_calendar_counts(): void
    {
        $technicalAbsence = SolicitudAusencia::create(['user_id' => $this->technical->id,
            'fecha' => '2026-11-02', 'tipo' => 'vacaciones', 'estado' => 'pendiente']);
        $employeeAbsence = SolicitudAusencia::create(['user_id' => $this->employee->id,
            'fecha' => '2026-11-03', 'tipo' => 'vacaciones', 'estado' => 'pendiente']);
        $this->get('/admin/solicitudes')->assertOk()->assertViewHas('solicitudes', fn ($items) =>
            $items->total() === 1 && $items->first()->id === $employeeAbsence->id);
        $this->get('/admin/vacaciones')->assertOk()->assertViewHas('eventos', fn ($events) =>
            !collect($events)->contains('id', $technicalAbsence->id)
            && collect($events)->contains('id', $employeeAbsence->id));
        $this->assertDatabaseHas('solicitud_ausencias', ['id' => $technicalAbsence->id]);
    }

    public function test_kiosk_excludes_technical_accounts_from_staff_and_upcoming_shifts(): void
    {
        Carbon::setTestNow('2026-10-30 12:45:00');
        HorarioSemanal::create([
            'semana_id' => '2026-W44', 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01',
            'estado' => 'publicado', 'vigente' => true, 'roles_publicados' => ['admin', 'auxiliar'],
            'plantilla_diseno' => [['dia' => 'viernes', 'turnos_requeridos' => [
                ['turno_id' => 'technical', 'hora_inicio' => '13:00', 'hora_fin' => '17:00'],
                ['turno_id' => 'employee', 'hora_inicio' => '13:00', 'hora_fin' => '17:00'],
            ]]],
            'resultados' => [
                ['empleado_id' => $this->technical->id, 'turno_id' => 'technical', 'nombre' => $this->technical->nombre],
                ['empleado_id' => $this->employee->id, 'turno_id' => 'employee', 'nombre' => $this->employee->nombre],
            ],
            'empleados_snapshot' => [$this->technical->toArray(), $this->employee->toArray()],
        ]);
        $this->getJson('/api/kiosko/status')->assertOk()->assertJsonCount(2, 'empleados')
            ->assertJsonMissing(['id' => $this->technical->id])->assertJsonCount(1, 'proximos_turnos')
            ->assertJsonPath('proximos_turnos.0.nombre', $this->employee->nombre);
    }

    public function test_cash_responsible_must_be_an_employee_while_the_technical_admin_can_save(): void
    {
        $payload = ['fecha' => '2026-10-30', 'turno' => 'cierre', 'billetes' => ['100' => '3'],
            'monedas' => [], 'billetes_ingreso' => [], 'monedas_ingreso' => [],
            'blisters' => ['total' => '0.00'], 'nota_blanca' => '0.00', 'fondo_fijo' => '300.00',
            'dni_empleado' => '99000000', 'expected_version' => 0, 'request_id' => (string) Str::uuid()];
        $this->postJson('/admin/cuadre', $payload)->assertUnprocessable()->assertJsonValidationErrors('dni_empleado');
        $this->manager->update(['dni' => '01234567B']);
        $payload['dni_empleado'] = '01234567';
        $this->postJson('/admin/cuadre', $payload)->assertOk()->assertJsonPath('saved.version', 1);
        $this->assertDatabaseHas('cuadre_cajas', ['created_by' => $this->technical->id, 'responsible_id' => $this->manager->id]);
    }
}
