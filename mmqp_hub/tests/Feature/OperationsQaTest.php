<?php

namespace Tests\Feature;

use App\Models\Fichaje;
use App\Models\HorarioSemanal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationsQaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true, 'rol' => 'oficina', 'activo' => true]);
        $this->employee = User::factory()->create([
            'es_admin' => false, 'rol' => 'repartidor', 'activo' => true,
            'nombre' => 'Empleado QA', 'dni' => '01234567L',
        ]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employeePayload(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Nueva persona QA', 'apellidos' => 'Pruebas', 'dni' => '23456789D',
            'email' => 'nueva.persona@example.test', 'password' => 'qa-password-123',
            'rol' => 'auxiliar', 'horas_mensuales' => 80, 'tipo_disponibilidad' => 'personalizada',
            'dias_disponibles' => ['lunes' => ['mañana'], 'martes' => ['tarde']],
        ], $overrides);
    }

    public function test_employee_can_be_created_without_login_credentials_and_reloaded(): void
    {
        $this->post('/admin/empleados/crear', $this->employeePayload(['email' => null, 'password' => null]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.empleados'));
        $created = User::where('dni', '23456789D')->firstOrFail();
        $this->assertNull($created->email);
        $this->assertNull($created->password);
        $this->assertTrue($created->activo);
        $this->assertFalse($created->es_admin);
        $this->assertSame(['lunes' => ['mañana'], 'martes' => ['tarde']], $created->dias_disponibles);
        $this->get('/admin/empleados/'.$created->id.'/editar')->assertOk()->assertViewHas('empleado', fn ($user) => $user->id === $created->id);
    }

    public function test_office_role_offered_in_employee_form_can_be_created_and_updated(): void
    {
        $this->get('/admin/empleados/crear')->assertOk()->assertSee('value="oficina"', false);
        $this->post('/admin/empleados/crear', $this->employeePayload(['rol' => 'oficina']))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.empleados'));
        $created = User::where('dni', '23456789D')->firstOrFail();
        $this->assertSame('oficina', $created->rol);
        $this->put('/admin/empleados/'.$created->id.'/editar', $this->employeePayload(['rol' => 'oficina', 'nombre' => 'Oficina actualizada']))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.empleados'));
        $this->assertSame('Oficina actualizada', $created->fresh()->nombre);
    }

    public function test_employee_password_is_hashed_and_blank_edit_preserves_it(): void
    {
        $payload = $this->employeePayload();
        $this->post('/admin/empleados/crear', $payload)->assertSessionHasNoErrors();
        $created = User::where('dni', $payload['dni'])->firstOrFail();
        $this->assertTrue(Hash::check($payload['password'], $created->password));
        $originalHash = $created->password;
        $this->put('/admin/empleados/'.$created->id.'/editar', array_replace($payload, ['password' => '']))->assertSessionHasNoErrors();
        $this->assertSame($originalHash, $created->fresh()->password);
        $this->put('/admin/empleados/'.$created->id.'/editar', array_replace($payload, ['password' => 'qa-new-password']))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('qa-new-password', $created->fresh()->password));
    }

    public function test_duplicate_employee_identity_is_rejected_without_changing_existing_users(): void
    {
        $before = $this->employee->fresh()->getRawOriginal();
        $this->postJson('/admin/empleados/crear', $this->employeePayload([
            'dni' => $this->employee->dni, 'email' => $this->employee->email,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['dni', 'email']);
        $this->putJson('/admin/empleados/'.$this->admin->id.'/editar', $this->employeePayload([
            'dni' => $this->employee->dni, 'email' => $this->employee->email,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['dni', 'email']);
        $this->assertSame($before, $this->employee->fresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_invalid_employee_fields_are_rejected_before_persistence(): void
    {
        $this->postJson('/admin/empleados/crear', $this->employeePayload([
            'nombre' => '', 'dni' => str_repeat('1', 21), 'email' => 'invalid',
            'password' => '123', 'rol' => 'invalid', 'horas_mensuales' => -1,
            'tipo_disponibilidad' => 'invalid',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'nombre', 'dni', 'email', 'password', 'rol', 'horas_mensuales', 'tipo_disponibilidad',
        ]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_malformed_availability_is_rejected_instead_of_breaking_employee_form(): void
    {
        $this->postJson('/admin/empleados/crear', $this->employeePayload(['dias_disponibles' => ['lunes' => 'mañana']]))
            ->assertUnprocessable()->assertJsonValidationErrors('dias_disponibles.lunes');
        $this->putJson('/admin/empleados/'.$this->employee->id.'/editar', $this->employeePayload(['dias_disponibles' => ['lunes' => ['noche']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('dias_disponibles.lunes.0');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_employee_search_role_and_active_filters_return_expected_people(): void
    {
        $inactive = User::factory()->create(['nombre' => 'Inactivo QA', 'rol' => 'limpiadora', 'activo' => false]);
        $this->get('/admin/empleados?buscar=Empleado%20QA&rol=repartidor')->assertOk()
            ->assertViewHas('empleados', fn ($rows) => $rows->pluck('id')->all() === [$this->employee->id]);
        $this->get('/admin/empleados')->assertOk()
            ->assertViewHas('empleados', fn ($rows) => !$rows->contains('id', $inactive->id));
        $this->get('/admin/empleados?estado=todos&rol=limpiadora')->assertOk()
            ->assertViewHas('empleados', fn ($rows) => $rows->pluck('id')->all() === [$inactive->id]);
    }

    public function test_employee_baja_and_active_toggles_persist_without_deleting_identity(): void
    {
        $this->post('/admin/empleados/'.$this->employee->id.'/baja', ['motivo' => 'Prueba QA'])->assertRedirect();
        $this->assertTrue($this->employee->fresh()->en_baja);
        $this->assertSame('Prueba QA', $this->employee->fresh()->baja_motivo);
        $this->get('/admin/empleados/activos')->assertOk()->assertViewHas('agrupados', fn ($groups) => !$groups->flatten()->contains('id', $this->employee->id));
        $this->post('/admin/empleados/'.$this->employee->id.'/baja')->assertRedirect();
        $this->assertFalse($this->employee->fresh()->en_baja);
        $this->assertNull($this->employee->fresh()->baja_desde);
        $this->post('/admin/empleados/'.$this->employee->id.'/activo')->assertRedirect();
        $this->assertFalse($this->employee->fresh()->activo);
        $this->post('/admin/empleados/'.$this->employee->id.'/activo')->assertRedirect();
        $this->assertTrue($this->employee->fresh()->activo);
    }

    public function test_employee_order_persists_and_admin_cannot_delete_their_own_account(): void
    {
        $this->postJson('/admin/empleados/orden', ['orden' => [
            ['id' => $this->employee->id, 'orden_visual' => 9], ['id' => $this->admin->id, 'orden_visual' => 1],
        ]])->assertOk()->assertJsonPath('success', true);
        $this->assertSame(9, $this->employee->fresh()->orden_visual);
        $this->delete('/admin/empleados/'.$this->admin->id)->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($this->admin->fresh());
        $this->delete('/admin/empleados/'.$this->employee->id)->assertRedirect()->assertSessionHas('success');
        $this->assertNull($this->employee->fresh());
    }

    public static function protectedOperations(): array
    {
        return [
            'employees list' => ['GET', '/admin/empleados', []],
            'employee create' => ['POST', '/admin/empleados/crear', []],
            'employee update' => ['PUT', '/admin/empleados/2/editar', []],
            'employee remove' => ['DELETE', '/admin/empleados/2', []],
            'employee active' => ['POST', '/admin/empleados/2/activo', []],
            'employee baja' => ['POST', '/admin/empleados/2/baja', []],
            'employee order' => ['POST', '/admin/empleados/orden', []],
            'clocking list' => ['GET', '/admin/fichajes', []],
            'clocking create' => ['POST', '/admin/fichajes', []],
            'clocking update' => ['PUT', '/admin/fichajes/1', []],
            'clocking remove' => ['DELETE', '/admin/fichajes/1', []],
            'extra shift' => ['POST', '/admin/fichajes/extra/2', []],
            'report list' => ['GET', '/admin/registro-jornada', []],
            'report data' => ['GET', '/admin/registro-jornada/informe', ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31']],
        ];
    }

    #[DataProvider('protectedOperations')]
    public function test_direct_admin_operations_reject_employee_role_even_if_role_name_is_admin(string $method, string $uri, array $data): void
    {
        $this->employee->update(['rol' => 'admin', 'es_admin' => false]);
        $this->actingAs($this->employee)->json($method, $uri, $data)->assertForbidden();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('fichajes', 0);
    }

    public function test_manual_clocking_supports_overnight_create_edit_reopen_and_delete(): void
    {
        $payload = ['user_id' => $this->employee->id, 'fecha' => '2026-10-23', 'entrada' => '22:30', 'salida' => '02:00', 'notas' => 'QA'];
        $created = $this->postJson('/admin/fichajes', $payload)->assertOk()->assertJsonPath('success', true)->json('fichaje');
        $clocking = Fichaje::findOrFail($created['id']);
        $this->assertSame('2026-10-23 22:30:00', $clocking->entrada->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-24 02:00:00', $clocking->salida->format('Y-m-d H:i:s'));
        $this->assertSame('3.50', $clocking->horas_calculadas);
        $this->assertSame('manual_admin', $clocking->origen);
        $this->putJson('/admin/fichajes/'.$clocking->id, array_replace($payload, ['salida' => '03:00']))->assertOk();
        $this->assertSame('4.50', $clocking->fresh()->horas_calculadas);
        $this->putJson('/admin/fichajes/'.$clocking->id, array_replace($payload, ['salida' => '']))->assertOk();
        $this->assertNull($clocking->fresh()->salida);
        $this->assertNull($clocking->fresh()->horas_calculadas);
        $this->get('/admin/fichajes?fecha=2026-10-23')->assertOk()
            ->assertViewHas('empleadosDict', fn ($employees) => count($employees[$this->employee->id]['fichajes_reales']) === 1);
        $this->delete('/admin/fichajes/'.$clocking->id)->assertRedirect();
        $this->assertDatabaseCount('fichajes', 0);
    }

    public static function invalidClockTimes(): array
    {
        return ['text entry' => ['entrada', 'invalid'], 'invalid entry hour' => ['entrada', '25:00'],
            'array entry' => ['entrada', ['10:00']], 'text exit' => ['salida', 'invalid'], 'invalid exit minute' => ['salida', '10:99']];
    }

    #[DataProvider('invalidClockTimes')]
    public function test_malformed_clock_times_are_validation_errors_and_never_change_existing_clockings(string $field, mixed $value): void
    {
        $clocking = Fichaje::create(['user_id' => $this->employee->id, 'entrada' => '2026-10-23 10:00:00']);
        $before = $clocking->fresh()->getRawOriginal();
        $payload = ['user_id' => $this->employee->id, 'fecha' => '2026-10-23', 'entrada' => '10:00', 'salida' => '15:00', $field => $value];
        $this->postJson('/admin/fichajes', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson('/admin/fichajes/'.$clocking->id, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $clocking->fresh()->getRawOriginal());
        $this->assertDatabaseCount('fichajes', 1);
    }

    public function test_invalid_clocking_date_filter_is_a_validation_error(): void
    {
        $this->getJson('/admin/fichajes?fecha=invalid')->assertUnprocessable()->assertJsonValidationErrors('fecha');
    }

    public function test_kiosk_correct_pin_opens_and_closes_a_shift_with_calculated_hours(): void
    {
        auth()->logout();
        Carbon::setTestNow('2026-10-23 22:00:00');
        $this->postJson('/api/kiosko/fichar', ['user_id' => $this->employee->id, 'pin' => '01234567'])
            ->assertOk()->assertJsonPath('action', 'entrada');
        $this->assertDatabaseCount('fichajes', 1);
        $this->getJson('/api/kiosko/status')->assertOk()->assertJsonFragment(['id' => $this->employee->id, 'is_working' => true]);
        Carbon::setTestNow('2026-10-24 02:30:00');
        $this->postJson('/api/kiosko/fichar', ['user_id' => $this->employee->id, 'pin' => '01234567'])
            ->assertOk()->assertJsonPath('action', 'salida')->assertJsonPath('horas', 4.5);
        $this->assertDatabaseCount('fichajes', 1);
        $this->assertSame('4.50', Fichaje::sole()->horas_calculadas);
        $this->assertSame('tablet_kiosko', Fichaje::sole()->origen);
        $this->getJson('/api/kiosko/status')->assertOk()->assertJsonFragment(['id' => $this->employee->id, 'is_working' => false]);
    }

    public function test_kiosk_wrong_pin_inactive_missing_dni_and_unknown_user_cannot_clock_in(): void
    {
        $this->postJson('/api/kiosko/fichar', ['user_id' => $this->employee->id, 'pin' => '99999999'])->assertUnauthorized();
        $this->employee->update(['activo' => false]);
        $this->postJson('/api/kiosko/fichar', ['user_id' => $this->employee->id, 'pin' => '01234567'])->assertUnauthorized();
        $this->getJson('/api/kiosko/status')->assertOk()->assertJsonMissing(['id' => $this->employee->id]);
        $this->employee->update(['activo' => true, 'dni' => null]);
        $this->postJson('/api/kiosko/fichar', ['user_id' => $this->employee->id, 'pin' => '01234567'])->assertUnauthorized();
        $this->postJson('/api/kiosko/fichar', ['user_id' => 99999, 'pin' => '01234567'])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('fichajes', 0);
    }

    private function publishedShift(string $date, string $day, string $start, string $end, array $overrides = []): HorarioSemanal
    {
        $id = 'qa-'.$date.'-'.$start;
        $fecha = Carbon::parse($date);
        return HorarioSemanal::create(array_replace([
            'semana_id' => $fecha->format('o-\WW'), 'fecha_inicio' => $fecha->copy()->startOfWeek()->toDateString(),
            'fecha_fin' => $fecha->copy()->endOfWeek()->toDateString(), 'estado' => 'published', 'vigente' => true,
            'roles_publicados' => ['repartidor'], 'generado_por' => $this->admin->id,
            'plantilla_diseno' => [['dia' => $day, 'fecha' => $date, 'turnos_requeridos' => [[
                'turno_id' => $id, 'hora_inicio' => $start, 'hora_fin' => $end, 'duracion_horas' => 4, 'rol_necesario' => 'repartidor',
            ]]]],
            'resultados' => [['turno_id' => $id, 'empleado_id' => $this->employee->id, 'nombre' => 'Empleado QA']],
            'empleados_snapshot' => [['id' => $this->employee->id, 'nombre' => 'Empleado QA', 'rol' => 'repartidor']],
        ], $overrides));
    }

    public function test_kiosk_upcoming_shift_uses_operational_schedule_and_ignores_drafts(): void
    {
        Carbon::setTestNow('2026-10-23 12:45:00');
        $this->publishedShift('2026-10-23', 'viernes', '13:00', '17:00');
        $this->publishedShift('2026-10-23', 'viernes', '13:10', '18:00', ['estado' => 'borrador', 'vigente' => false, 'draft_slot' => 1]);
        $this->getJson('/api/kiosko/status')->assertOk()
            ->assertJsonPath('proximos_turnos.0.tipo', 'entra')->assertJsonPath('proximos_turnos.0.hora', '13:00')
            ->assertJsonCount(1, 'proximos_turnos');
    }

    public function test_kiosk_upcoming_exit_includes_overnight_shift_from_previous_week(): void
    {
        Carbon::setTestNow('2026-10-26 00:45:00');
        $this->publishedShift('2026-10-25', 'domingo', '22:00', '01:00');
        $this->getJson('/api/kiosko/status')->assertOk()->assertJsonFragment([
            'tipo' => 'sale', 'nombre' => 'Empleado QA', 'hora' => '01:00',
        ]);
    }

    public function test_kiosk_upcoming_window_includes_tomorrow_start_at_iso_week_boundary(): void
    {
        Carbon::setTestNow('2026-10-25 23:45:00');
        $this->publishedShift('2026-10-26', 'lunes', '00:00', '04:00');
        $this->getJson('/api/kiosko/status')->assertOk()->assertJsonFragment([
            'tipo' => 'entra', 'nombre' => 'Empleado QA', 'hora' => '00:00',
        ]);
    }

    public function test_master_preserves_iso_week_history_from_previous_calendar_year(): void
    {
        Carbon::setTestNow('2027-01-01 12:00:00');
        $schedule = $this->publishedShift('2027-01-01', 'viernes', '13:00', '17:00');
        $before = $schedule->fresh()->getRawOriginal();
        $this->get('/admin/empleados')->assertOk()->assertViewMissing('horasMensuales')->assertDontSee('Control de Horas');
        $this->assertSame($before, $schedule->fresh()->getRawOriginal());
    }

    public function test_master_preserves_history_with_accented_days_at_month_boundary(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');
        $schedule = $this->publishedShift('2026-07-01', 'Miércoles', '13:00', '17:00');
        $before = $schedule->fresh()->getRawOriginal();
        $this->get('/admin/empleados')->assertOk()->assertViewMissing('horasMensuales')->assertDontSee('Control de Horas');
        $this->assertSame($before, $schedule->fresh()->getRawOriginal());
    }

    public static function reportTypes(): array
    {
        return ['normal' => ['normal'], 'resumido' => ['resumido'], 'fichaje' => ['fichaje']];
    }

    #[DataProvider('reportTypes')]
    public function test_reports_filter_user_and_range_and_preserve_open_clockings(string $type): void
    {
        Fichaje::create(['user_id' => $this->employee->id, 'entrada' => '2026-10-23 22:00:00', 'salida' => '2026-10-24 02:00:00', 'horas_calculadas' => 4]);
        Fichaje::create(['user_id' => $this->employee->id, 'entrada' => '2026-10-24 12:00:00']);
        Fichaje::create(['user_id' => $this->employee->id, 'entrada' => '2026-09-30 12:00:00', 'salida' => '2026-09-30 22:00:00', 'horas_calculadas' => 10]);
        $other = User::factory()->create(['es_admin' => false]);
        Fichaje::create(['user_id' => $other->id, 'entrada' => '2026-10-23 10:00:00', 'salida' => '2026-10-23 20:00:00', 'horas_calculadas' => 10]);
        $response = $this->get('/admin/registro-jornada/informe?'.http_build_query([
            'fecha_inicio' => '2026-10-23', 'fecha_fin' => '2026-10-24', 'empleado_id' => $this->employee->id, 'tipo_informe' => $type,
        ]))->assertOk();
        if ($type === 'fichaje') {
            $response->assertViewHas('fichajesTotales', fn ($rows) => count($rows) === 2 && $rows[0]['horas'] === '4.00' && $rows[1]['salida'] === '-');
        } elseif ($type === 'resumido') {
            $response->assertViewHas('reporte', fn ($rows) => count($rows) === 1 && $rows[0]['empleado']->id === $this->employee->id && (float) $rows[0]['total_horas'] === 4.0);
        } else {
            $response->assertViewHas('reporte', fn ($rows) => count($rows) === 1 && count($rows[0]['dias']) === 2 && (float) $rows[0]['total_ordinarias'] === 4.0 && $rows[0]['dias'][1]['salida'] === '-');
        }
    }

    #[DataProvider('reportTypes')]
    public function test_reports_render_empty_period_without_fabricating_hours(string $type): void
    {
        $response = $this->get('/admin/registro-jornada/informe?'.http_build_query([
            'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-02', 'empleado_id' => $this->employee->id, 'tipo_informe' => $type,
        ]))->assertOk();
        if ($type === 'fichaje') {
            $response->assertViewHas('fichajesTotales', []);
        } elseif ($type === 'resumido') {
            $response->assertViewHas('reporte', fn ($rows) => count($rows) === 1 && $rows[0]['total_horas'] === 0);
        } else {
            $response->assertViewHas('reporte', fn ($rows) => count($rows) === 1 && count($rows[0]['dias']) === 2 && $rows[0]['total_ordinarias'] === 0);
        }
    }

    public function test_reports_reject_reverse_ranges_unknown_employees_and_unknown_types(): void
    {
        $this->getJson('/admin/registro-jornada/informe?fecha_inicio=2026-10-24&fecha_fin=2026-10-23&empleado_id=99999&tipo_informe=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha_fin', 'empleado_id', 'tipo_informe']);
    }

    public function test_demo_blocks_push_subscriptions_for_employees_and_administrators(): void
    {
        $endpoint = 'https://push.example.test/qa-endpoint';
        $payload = ['endpoint' => $endpoint, 'keys' => ['auth' => 'qa-auth', 'p256dh' => 'qa-key']];
        foreach ([$this->employee, $this->admin] as $user) {
            $this->actingAs($user)->postJson('/push/subscribe', $payload)->assertForbidden();
            $this->postJson('/push/unsubscribe', ['endpoint' => $endpoint])->assertForbidden();
            $this->assertDatabaseCount('push_subscriptions', 0);
        }
    }

    public function test_demo_blocks_push_subscriptions_for_guests_without_persisting_endpoints(): void
    {
        $payload = ['endpoint' => 'https://push.example.test/shared-browser', 'keys' => ['auth' => 'qa-auth', 'p256dh' => 'qa-key']];
        auth()->logout();
        $this->postJson('/push/subscribe', $payload)->assertForbidden();
        $this->postJson('/push/unsubscribe', ['endpoint' => $payload['endpoint']])->assertForbidden();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_demo_blocks_incomplete_push_payloads_before_processing_them(): void
    {
        $this->postJson('/push/subscribe', ['endpoint' => 'https://push.example.test/qa'])->assertForbidden();
        $this->postJson('/push/unsubscribe', [])->assertForbidden();
        auth()->logout();
        $this->postJson('/push/subscribe', [])->assertForbidden();
        $this->postJson('/push/unsubscribe', [])->assertForbidden();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
