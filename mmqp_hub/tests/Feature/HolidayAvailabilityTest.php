<?php

namespace Tests\Feature;

use App\Models\Festivo;
use App\Models\User;
use App\Services\EmployeeAvailability;
use App\Services\HolidayCalendar;
use App\Services\ScheduleDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HolidayAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true, 'rol' => 'oficina', 'activo' => true]);
        $this->actingAs($this->admin);
    }

    private function employee(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['rol' => 'auxiliar', 'activo' => true, 'en_baja' => false,
            'tipo_disponibilidad' => 'personalizada', 'dias_disponibles' => ['martes' => ['tarde']]]);
    }

    private function holiday(): void
    {
        Festivo::create(['fecha' => '2026-10-19', 'nombre' => 'Festivo del centro']);
    }

    private function layout(?int $employeeId = null): array
    {
        return [['dia' => 'lunes', 'fecha' => '2026-10-19', 'turnos_requeridos' => [[
            'turno_id' => 'holiday-shift', 'rol_necesario' => 'auxiliar', 'hora_inicio' => '12:00',
            'hora_fin' => '16:00', 'duracion_horas' => 4, 'empleado_id' => $employeeId,
        ]]]];
    }

    private function employeeData(User $employee, string $holidayMode): array
    {
        return ['nombre' => $employee->nombre, 'apellidos' => $employee->apellidos, 'email' => $employee->email,
            'rol' => 'auxiliar', 'tipo_disponibilidad' => 'personalizada',
            'dias_disponibles' => ['martes' => ['tarde']], 'disponibilidad_festivos' => $holidayMode];
    }

    public function test_calendar_has_no_inferred_weekends_and_admin_can_add_edit_and_remove_dates(): void
    {
        $this->assertSame([], app(HolidayCalendar::class)->dates());
        $this->get('/admin/festivos')->assertOk()->assertSee('No hay festivos configurados');
        $this->put('/admin/festivos', ['nuevo' => ['fecha' => '2026-10-19', 'nombre' => 'Festivo local']])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/festivos');
        $this->assertSame(['2026-10-19'], app(HolidayCalendar::class)->dates());
        $this->assertFalse(app(HolidayCalendar::class)->isHoliday('2026-10-18'));
        $this->put('/admin/festivos', ['festivos' => [['fecha' => '2026-10-20', 'nombre' => 'Fecha corregida']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['2026-10-20'], app(HolidayCalendar::class)->dates());
        $this->get('/admin/festivos')->assertOk()->assertSee('Fecha corregida')->assertSee('2026-10-20');
        $this->put('/admin/festivos', ['festivos' => [['fecha' => '2026-10-20', 'nombre' => 'Fecha corregida', 'eliminar' => 1]]])
            ->assertSessionHasNoErrors();
        $this->assertSame([], app(HolidayCalendar::class)->dates());
    }

    public function test_invalid_or_duplicate_calendar_dates_do_not_change_stored_calendar(): void
    {
        $this->holiday();
        foreach ([
            [['fecha' => '2026-02-30', 'nombre' => 'Inválido']],
            [['fecha' => '2026-10-19', 'nombre' => 'Uno'], ['fecha' => '2026-10-19', 'nombre' => 'Dos']],
        ] as $rows) {
            $this->putJson('/admin/festivos', ['festivos' => $rows])->assertUnprocessable();
            $this->assertSame(['2026-10-19'], app(HolidayCalendar::class)->dates());
        }
    }

    public function test_only_administrators_can_read_or_change_holiday_calendar_or_employee_settings(): void
    {
        $employee = $this->employee();
        $this->actingAs($employee)->get('/admin/festivos')->assertForbidden();
        $this->putJson('/admin/festivos', ['nuevo' => ['fecha' => '2026-10-19', 'nombre' => 'Prueba']])->assertForbidden();
        $this->putJson('/admin/empleados/'.$employee->id.'/editar', $this->employeeData($employee, 'disponible'))->assertForbidden();
        $this->assertSame([], app(HolidayCalendar::class)->dates());
    }

    public function test_admin_setting_persists_independently_of_weekly_availability_and_is_visible_on_reopening(): void
    {
        $employee = $this->employee();
        $this->assertSame('semanal', $employee->fresh()->disponibilidad_festivos);
        foreach (['disponible', 'no_disponible', 'semanal'] as $mode) {
            $this->put('/admin/empleados/'.$employee->id.'/editar', $this->employeeData($employee, $mode))
                ->assertSessionHasNoErrors()->assertRedirect('/admin/empleados');
            $employee->refresh();
            $this->assertSame($mode, $employee->disponibilidad_festivos);
            $this->assertSame(['martes' => ['tarde']], $employee->dias_disponibles);
            $this->get('/admin/empleados/'.$employee->id.'/editar')->assertOk()->assertSee('Disponibilidad en festivos');
        }
        $this->putJson('/admin/empleados/'.$employee->id.'/editar', $this->employeeData($employee, 'cualquiera'))
            ->assertUnprocessable()->assertJsonValidationErrors('disponibilidad_festivos');
        $this->assertSame('semanal', $employee->fresh()->disponibilidad_festivos);
    }

    public function test_legacy_admin_requests_do_not_reset_a_previously_saved_holiday_preference(): void
    {
        $employee = $this->employee(['disponibilidad_festivos' => 'no_disponible']);
        $data = $this->employeeData($employee, 'disponible');
        unset($data['disponibilidad_festivos']);
        $this->put('/admin/empleados/'.$employee->id.'/editar', $data)->assertSessionHasNoErrors();
        $this->assertSame('no_disponible', $employee->fresh()->disponibilidad_festivos);
    }

    public function test_admin_can_create_an_employee_with_holiday_preference_without_weekly_available_days(): void
    {
        $this->post('/admin/empleados/crear', ['nombre' => 'Festivo Nuevo', 'rol' => 'auxiliar',
            'tipo_disponibilidad' => 'personalizada', 'disponibilidad_festivos' => 'disponible'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/empleados');
        $employee = User::where('nombre', 'Festivo Nuevo')->firstOrFail();
        $this->assertSame('disponible', $employee->disponibilidad_festivos);
        $this->assertSame([], $employee->dias_disponibles);
    }

    public function test_dated_availability_distinguishes_holidays_from_normal_weekdays_and_preserves_weekly_pattern(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'disponible', 'dias_bloqueados_recurrentes' => ['lunes']]);
        $availability = app(EmployeeAvailability::class);
        $this->assertTrue($availability->isAvailable($employee, '2026-10-19', 'mañana'));
        $this->assertFalse($availability->isAvailable($employee, '2026-10-26', 'mañana'));
        $this->assertTrue($availability->isAvailable($employee, '2026-10-20', 'tarde'));
        $employee->update(['disponibilidad_festivos' => 'no_disponible']);
        $this->assertFalse($availability->isAvailable($employee, '2026-10-19', 'tarde'));
        $this->assertTrue($availability->isAvailable($employee, '2026-10-20', 'tarde'));
        $employee->update(['disponibilidad_festivos' => 'semanal']);
        $this->assertFalse($availability->isAvailable($employee, '2026-10-19', 'tarde'));
    }

    public function test_holiday_opt_in_never_cancels_approved_absence_or_medical_leave(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'disponible']);
        $employee->solicitudesAusencia()->create(['fecha' => '2026-10-19', 'fecha_fin' => '2026-10-19',
            'tipo' => 'vacaciones', 'estado' => 'aprobada']);
        $this->assertFalse(app(EmployeeAvailability::class)->isAvailable($employee->fresh(), '2026-10-19'));
        $employee->solicitudesAusencia()->delete();
        $employee->update(['en_baja' => true]);
        $this->assertFalse(app(EmployeeAvailability::class)->isAvailable($employee->fresh(), '2026-10-19'));
    }

    public function test_employee_profile_shows_current_weekly_and_holiday_settings_without_changing_absence_requests(): void
    {
        $employee = $this->employee(['disponibilidad_festivos' => 'no_disponible']);
        $this->actingAs($employee)->get('/perfil/disponibilidad')->assertOk()
            ->assertSeeInOrder(['Disponibilidad semanal', 'Disponibilidad en festivos', 'No disponible para trabajar'])
            ->assertSee('Solicitar Día Libre');
    }

    public function test_generator_uses_database_holidays_and_employee_preferences_despite_forged_client_metadata(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'no_disponible']);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        Http::fake(['127.0.0.1:5000/*' => Http::response(['success' => true, 'resultados' => []])]);
        $this->postJson('/api/generar-horario', ['draft_id' => $draft->id, 'version' => $draft->version,
            'request_id' => (string) Str::uuid(), 'ajustes_manuales' => [$employee->id => 4],
            'empleados' => [['id' => $employee->id, 'disponibilidad_festivos' => 'disponible']],
            'metadatos' => ['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25', 'festivos' => []],
            'plantilla_diseno' => $this->layout(),
        ])->assertOk()->assertJsonPath('success', true);
        Http::assertSent(fn ($request) => $request['metadatos']['festivos'] === ['2026-10-19']
            && $request['empleados'][0]['disponibilidad_festivos'] === 'no_disponible');
    }

    public function test_forged_manual_draft_assignment_is_rejected_using_real_week_and_database_preference(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'no_disponible']);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        $layout = $this->layout($employee->id);
        $layout[0]['fecha'] = '2026-10-20'; // A client cannot change Monday into a non-holiday.
        $this->postJson('/api/horarios-semanales/borrador', ['draft_id' => $draft->id, 'semana_id' => '2026-W43',
            'version' => $draft->version, 'request_id' => (string) Str::uuid(),
            'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25', 'plantilla_diseno' => $layout,
            'empleados_snapshot' => [['id' => $employee->id, 'disponibilidad_festivos' => 'disponible']],
        ])->assertUnprocessable()->assertJsonValidationErrors('plantilla_diseno');
        $this->assertSame(1, $draft->fresh()->version);
    }

    public function test_generator_rejects_preassigned_holiday_opt_out_before_calling_solver(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'no_disponible']);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        Http::fake();
        $this->postJson('/api/generar-horario', ['draft_id' => $draft->id, 'version' => $draft->version,
            'request_id' => (string) Str::uuid(), 'ajustes_manuales' => [$employee->id => 4],
            'metadatos' => ['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25', 'festivos' => []],
            'plantilla_diseno' => $this->layout($employee->id),
        ])->assertUnprocessable()->assertJsonValidationErrors('plantilla_diseno');
        Http::assertNothingSent();
    }

    public function test_real_auto_template_uses_configured_calendar_and_current_holiday_setting(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'disponible', 'horas_mensuales' => 16]);
        $hours = array_fill_keys(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'], []);
        $hours['lunes'] = [['inicio' => '12:00', 'fin' => '16:00']];
        $payload = ['semana' => '2026-W43', 'horario' => $hours, 'festivos' => []];
        $this->postJson('/api/generar-plantilla-ia', $payload)->assertOk()
            ->assertJsonPath('resumen.0.id', $employee->id)->assertJsonPath('resumen.0.minutos_generados', 240);
        $employee->update(['disponibilidad_festivos' => 'no_disponible']);
        $this->postJson('/api/generar-plantilla-ia', $payload)->assertOk()->assertJsonPath('resumen.0.minutos_generados', 0);
        $employee->update(['disponibilidad_festivos' => 'disponible']);
        Festivo::query()->delete();
        $payload['festivos'] = ['2026-10-19']; // Untrusted client calendar cannot turn a normal Monday into a holiday.
        $this->postJson('/api/generar-plantilla-ia', $payload)->assertOk()->assertJsonPath('resumen.0.minutos_generados', 0);
    }

    public function test_publication_rechecks_holiday_preference_changed_after_draft_was_saved(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'disponible']);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        $draft = app(ScheduleDrafts::class)->save($draft->id, '2026-W43', $draft->version, (string) Str::uuid(), [
            'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25', 'plantilla_diseno' => $this->layout($employee->id),
            'empleados_snapshot' => [['id' => $employee->id]],
        ]);
        $employee->update(['disponibilidad_festivos' => 'no_disponible']);
        $this->postJson('/admin/horarios/resultado/'.$draft->id.'/publicar', [
            'semana_id' => '2026-W43', 'version' => $draft->version, 'roles' => ['auxiliar'],
        ])->assertUnprocessable()->assertJsonValidationErrors('plantilla_diseno');
        $this->assertSame('borrador', $draft->fresh()->estado);
    }

    public function test_manual_save_and_publication_reject_holiday_opt_in_when_absence_was_approved_after_save(): void
    {
        $this->holiday();
        $employee = $this->employee(['disponibilidad_festivos' => 'disponible']);
        $draft = app(ScheduleDrafts::class)->open('2026-W43');
        $content = ['fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25',
            'plantilla_diseno' => $this->layout($employee->id), 'empleados_snapshot' => [['id' => $employee->id]]];
        $draft = app(ScheduleDrafts::class)->save($draft->id, '2026-W43', $draft->version, (string) Str::uuid(), $content);
        $employee->solicitudesAusencia()->create(['fecha' => '2026-10-19', 'fecha_fin' => '2026-10-19',
            'tipo' => 'vacaciones', 'estado' => 'aprobada']);
        $this->postJson('/api/horarios-semanales/borrador', $content + ['draft_id' => $draft->id, 'semana_id' => '2026-W43',
            'version' => $draft->version, 'request_id' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('plantilla_diseno');
        $this->postJson('/admin/horarios/resultado/'.$draft->id.'/publicar', [
            'semana_id' => '2026-W43', 'version' => $draft->version, 'roles' => ['auxiliar'],
        ])->assertUnprocessable()->assertJsonValidationErrors('plantilla_diseno');
        $this->assertSame('borrador', $draft->fresh()->estado);
        $this->assertSame(2, $draft->version);
    }
}
