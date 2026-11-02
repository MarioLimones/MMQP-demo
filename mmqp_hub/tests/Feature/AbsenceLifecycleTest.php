<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\SolicitudAusencia;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsenceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true]);
        $this->employee = User::factory()->create();
    }

    private function payload(string $start = '2026-11-02', ?string $end = '2026-11-04', string $type = 'vacaciones'): array
    {
        return ['user_id' => $this->employee->id, 'fecha' => $start, 'fecha_fin' => $end,
            'tipo' => $type, 'request_key' => (string) Str::uuid()];
    }

    private function assign(array $data = []): SolicitudAusencia
    {
        $this->actingAs($this->admin)->post('/admin/vacaciones', $data ?: $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        return SolicitudAusencia::latest('id')->firstOrFail();
    }

    private function blocked(): array
    {
        return $this->employee->fresh()->fechas_libres_solicitadas;
    }

    public function test_assignment_is_shared_by_calendar_profile_and_designer_after_new_session(): void
    {
        $absence = $this->assign();
        $dates = ['2026-11-02', '2026-11-03', '2026-11-04'];
        $this->assertSame($dates, $this->blocked());
        $this->assertNull($this->employee->fresh()->getRawOriginal('vacaciones'));
        $this->assertNull($this->employee->fresh()->getRawOriginal('fechas_libres_solicitadas'));
        $this->get('/admin/vacaciones')->assertOk()->assertViewHas('eventos', fn ($events) => $events[0]['id'] === $absence->id && $events[0]['end'] === '2026-11-05' && $events[0]['extendedProps']['version'] === 1);
        $this->get('/admin/horarios')->assertOk()->assertViewHas('empleados', fn ($users) => $users->find($this->employee->id)->vacaciones === $dates)
            ->assertViewHas('ausencias', fn ($rows) => $rows->first()->fecha_fin->format('Y-m-d') === '2026-11-04');
        $this->actingAs($this->employee->fresh())->get('/perfil/vacaciones')->assertViewHas('vacaciones_json', json_encode($dates));
        $this->flushSession();
        $this->actingAs($this->employee->fresh())->get('/perfil/vacaciones')->assertOk()->assertViewHas('vacaciones_json', json_encode($dates));
    }

    public function test_move_expand_and_reduce_remove_all_old_dates(): void
    {
        $absence = $this->assign();
        foreach ([['2026-11-08', '2026-11-09', ['2026-11-08', '2026-11-09']],
            ['2026-11-07', '2026-11-10', ['2026-11-07', '2026-11-08', '2026-11-09', '2026-11-10']],
            ['2026-11-09', '2026-11-09', ['2026-11-09']]] as [$start, $end, $dates]) {
            $this->put('/admin/vacaciones/'.$absence->id, $this->payload($start, $end) + ['version' => $absence->version])
                ->assertSessionHasNoErrors()->assertRedirect();
            $absence->refresh();
            $this->assertSame($dates, $this->blocked());
            $this->assertSame($dates, $this->employee->fresh()->vacaciones);
        }
    }

    public function test_pending_and_rejected_edits_do_not_block_and_approval_covers_the_whole_range(): void
    {
        $absence = SolicitudAusencia::create(['user_id' => $this->employee->id, 'fecha' => '2026-11-02',
            'fecha_fin' => '2026-11-04', 'tipo' => 'dia_libre', 'estado' => 'pendiente']);
        $this->actingAs($this->admin)->put('/admin/vacaciones/'.$absence->id, $this->payload('2026-11-05', '2026-11-07', 'dia_libre') + ['version' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame([], $this->blocked());
        $this->put('/admin/solicitudes/'.$absence->id, ['estado' => 'aprobada', 'version' => 2])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-05', '2026-11-06', '2026-11-07'], $this->blocked());
        $this->put('/admin/solicitudes/'.$absence->id, ['estado' => 'rechazada', 'version' => 3])->assertSessionHasNoErrors();
        $this->put('/admin/vacaciones/'.$absence->id, $this->payload('2026-11-10', null, 'dia_libre') + ['version' => 4])->assertSessionHasNoErrors();
        $this->assertSame([], $this->blocked());
        $this->assertSame('rechazada', $absence->fresh()->estado);
    }

    public function test_cancelling_and_deleting_preserve_other_active_causes(): void
    {
        $first = $this->assign();
        $second = $this->assign($this->payload('2026-11-03', '2026-11-05', 'baja'));
        $this->put('/admin/solicitudes/'.$first->id, ['estado' => 'cancelada', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-03', '2026-11-04', '2026-11-05'], $this->blocked());
        $this->assertSame([], $this->employee->fresh()->vacaciones);
        $this->delete('/admin/vacaciones/'.$first->id, ['version' => 2])->assertRedirect();
        $this->assertSoftDeleted('solicitud_ausencias', ['id' => $first->id]);
        $this->assertSame(['2026-11-03', '2026-11-04', '2026-11-05'], $this->blocked());
        $this->delete('/admin/vacaciones/'.$second->id, ['version' => 1]);
        $this->assertSame([], $this->blocked());
        $this->delete('/admin/vacaciones/'.$second->id, ['version' => 1])->assertRedirect();
        $this->assertSame(2, $second->fresh()->version);
    }

    public function test_single_day_and_dst_range_include_both_limits_and_weekend(): void
    {
        config(['app.timezone' => 'Europe/Madrid']);
        $single = $this->assign($this->payload('2026-10-25', null));
        $this->assertSame(['2026-10-25'], $this->blocked());
        $this->put('/admin/vacaciones/'.$single->id, $this->payload('2026-10-24', '2026-10-26') + ['version' => 1]);
        $this->assertSame(['2026-10-24', '2026-10-25', '2026-10-26'], $this->blocked());
    }

    public function test_changing_type_updates_vacations_without_losing_other_restrictions(): void
    {
        $this->employee->update(['en_baja' => true, 'dias_bloqueados_recurrentes' => ['domingo'],
            'tipo_disponibilidad' => 'personalizada', 'dias_disponibles' => ['lunes' => ['manana']]]);
        $absence = $this->assign();
        $this->put('/admin/vacaciones/'.$absence->id, $this->payload('2026-11-02', '2026-11-04', 'baja') + ['version' => 1]);
        $this->assertSame([], $this->employee->fresh()->vacaciones);
        $this->assertCount(3, $this->blocked());
        $this->delete('/admin/vacaciones/'.$absence->id, ['version' => 2]);
        $this->assertTrue($this->employee->fresh()->en_baja);
        $this->assertSame(['domingo'], $this->employee->fresh()->dias_bloqueados_recurrentes);
        $this->assertSame(['lunes' => ['manana']], $this->employee->fresh()->dias_disponibles);
    }

    public function test_repeated_create_has_one_record_and_changed_payload_is_rejected(): void
    {
        $data = $this->payload();
        $absence = $this->assign($data);
        $this->post('/admin/vacaciones', $data)->assertRedirect();
        $this->assertSame(1, SolicitudAusencia::count());
        $changed = $data;
        $changed['fecha_fin'] = '2026-11-05';
        $this->post('/admin/vacaciones', $changed)->assertStatus(409);
        $this->delete('/admin/vacaciones/'.$absence->id, ['version' => 1]);
        $this->post('/admin/vacaciones', $data)->assertRedirect();
        $this->assertSame(1, SolicitudAusencia::withTrashed()->count());
        $this->assertSame(0, SolicitudAusencia::count());
        $this->assertSame([], $this->blocked());
    }

    public function test_concurrent_forms_cannot_overwrite_a_newer_edit_or_delete_it(): void
    {
        $absence = $this->assign();
        $this->put('/admin/vacaciones/'.$absence->id, $this->payload('2026-11-07', null) + ['version' => 1])->assertRedirect();
        $this->put('/admin/vacaciones/'.$absence->id, $this->payload('2026-11-08', null) + ['version' => 1])->assertStatus(409);
        $this->delete('/admin/vacaciones/'.$absence->id, ['version' => 1])->assertStatus(409);
        $this->put('/admin/solicitudes/'.$absence->id, ['estado' => 'rechazada', 'version' => 1])->assertStatus(409);
        $this->assertSame(['2026-11-07'], $this->blocked());
        $this->assertSame(2, $absence->fresh()->version);
    }

    public function test_equivalent_single_day_payloads_reuse_the_same_submission(): void
    {
        $data = $this->payload('2026-11-02', null);
        $this->assign($data);
        unset($data['fecha_fin']);
        $data['request_key'] = strtoupper($data['request_key']);
        $this->post('/admin/vacaciones', $data)->assertRedirect();
        $this->assertSame(1, SolicitudAusencia::count());
        $this->assertSame(['2026-11-02'], $this->blocked());
    }

    public function test_invalid_dates_and_missing_concurrency_tokens_do_not_change_data(): void
    {
        $absence = $this->assign();
        foreach ([['2026-11-04', '2026-11-02'], ['2026-02-30', null], ['2026-11-02T23:00:00Z', null]] as [$start, $end]) {
            $this->putJson('/admin/vacaciones/'.$absence->id, $this->payload($start, $end) + ['version' => 1])->assertUnprocessable();
        }
        $this->putJson('/admin/vacaciones/'.$absence->id, $this->payload())->assertUnprocessable();
        $this->deleteJson('/admin/vacaciones/'.$absence->id)->assertUnprocessable();
        $data = $this->payload();
        unset($data['request_key']);
        $this->postJson('/admin/vacaciones', $data)->assertUnprocessable();
        $this->assertSame(1, $absence->fresh()->version);
        $this->assertCount(3, $this->blocked());
    }

    public function test_employee_cannot_read_or_change_other_employees_absences(): void
    {
        $absence = $this->assign();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/admin/vacaciones')->assertForbidden();
        $this->get('/admin/solicitudes')->assertForbidden();
        $this->post('/admin/vacaciones', $this->payload())->assertForbidden();
        $this->put('/admin/vacaciones/'.$absence->id, $this->payload() + ['version' => 1])->assertForbidden();
        $this->put('/admin/solicitudes/'.$absence->id, ['estado' => 'rechazada', 'version' => 1])->assertForbidden();
        $this->delete('/admin/vacaciones/'.$absence->id, ['version' => 1])->assertForbidden();
        $this->get('/perfil/vacaciones?user_id='.$this->employee->id)->assertViewHas('vacaciones_json', '[]');
        $this->get('/perfil/disponibilidad?user_id='.$this->employee->id)->assertViewHas('solicitudes', fn ($rows) => $rows->isEmpty());
        $this->assertCount(3, $this->blocked());
    }

    public function test_employee_request_uses_own_identity_and_existing_duplicate_rule(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 10));
        $data = $this->payload() + ['estado' => 'aprobada'];
        $data['user_id'] = $this->admin->id;
        $this->actingAs($this->employee)->post('/perfil/disponibilidad', $data)->assertSessionHasNoErrors();
        $this->post('/perfil/disponibilidad', $data)->assertSessionHasNoErrors();
        $absence = SolicitudAusencia::first();
        $this->assertSame($this->employee->id, $absence->user_id);
        $this->assertSame('pendiente', $absence->estado);
        $this->assertSame('dia_libre', $absence->tipo);
        $this->assertSame('2026-11-02', $absence->fecha_fin->format('Y-m-d'));
        $this->assertSame([], $this->blocked());
        $data['request_key'] = (string) Str::uuid();
        $this->post('/perfil/disponibilidad', $data)->assertSessionHasErrors('fecha');
        $data['fecha'] = '2026-10-09';
        $this->post('/perfil/disponibilidad', $data)->assertSessionHasErrors('fecha');
        $this->assertSame(1, SolicitudAusencia::count());
    }

    public function test_failed_save_rolls_back_record_and_employee_lock(): void
    {
        $event = 'eloquent.saving: '.SolicitudAusencia::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated storage failure'));
        try {
            app(AbsenceService::class)->create($this->admin, $this->payload());
            $this->fail('Expected save failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(0, SolicitudAusencia::count());
        $this->assertSame(0, $this->employee->fresh()->absence_write_lock);
        $this->assertSame([], $this->blocked());
    }

    public function test_published_assignments_are_preserved_and_conflicts_are_visible(): void
    {
        $schedule = $this->schedule();
        $original = DB::table('horarios_semanales')->where('id', $schedule->id)->first();
        $absence = $this->assign();
        $this->assertEquals($original, DB::table('horarios_semanales')->where('id', $schedule->id)->first());
        $this->get('/admin/vacaciones')->assertSee('Hay turnos publicados')->assertSee('2026-11-04');
        $this->get('/admin/horarios/resultado/'.$schedule->id)->assertOk()
            ->assertViewHas('data', fn ($data) => $data['empleados'][0]['vacaciones'] === ['2026-11-02', '2026-11-03', '2026-11-04'] && count($data['absence_conflicts']) === 1);
        $this->put('/admin/solicitudes/'.$absence->id, ['estado' => 'rechazada', 'version' => 1]);
        $this->get('/admin/horarios/resultado/'.$schedule->id)->assertViewHas('data', fn ($data) => $data['absence_conflicts'] === []);
        $this->assertEquals($original, DB::table('horarios_semanales')->where('id', $schedule->id)->first());
    }

    public function test_opening_an_old_draft_refreshes_availability_without_writing_its_snapshot(): void
    {
        $source = $this->schedule();
        $this->assign();
        $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => '2026-W45', 'source_id' => $source->id])
            ->assertOk()->assertJsonPath('draft.empleados_snapshot.0.vacaciones', ['2026-11-02', '2026-11-03', '2026-11-04']);
        $this->assertSame(['2000-01-01'], $source->fresh()->empleados_snapshot[0]['vacaciones']);
    }

    public function test_generator_receives_authoritative_dates_and_rejects_non_iso_dates(): void
    {
        $this->assign();
        $draft = $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => '2026-W45'])->assertOk()->json('draft');
        Http::fake(['http://127.0.0.1:5000/generar' => Http::response(['success' => true, 'resultados' => []])]);
        $payload = ['metadatos' => ['semana_id' => '2026-W45', 'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-08'],
            'ajustes_manuales' => [$this->employee->id => 8],
            'plantilla_diseno' => [['dia' => 'lunes', 'fecha' => '2026-11-02', 'turnos_requeridos' => []]],
            'draft_id' => $draft['id'], 'version' => $draft['version'], 'request_id' => (string) Str::uuid()];
        $this->postJson('/api/generar-horario', $payload)->assertOk()->assertJsonPath('success', true);
        Http::assertSent(function ($request) {
            $employees = collect($request['empleados']);
            $employee = $employees->firstWhere('id', $this->employee->id);

            return $employee['vacaciones'] === ['2026-11-02', '2026-11-03', '2026-11-04'] &&
                $employee['fechas_libres_solicitadas'] === $employee['vacaciones'] && $request['plantilla_diseno'][0]['fecha'] === '2026-11-02';
        });
        $payload['version']++;
        $payload['request_id'] = (string) Str::uuid();
        $payload['plantilla_diseno'][0]['fecha'] = '2 nov';
        $this->postJson('/api/generar-horario', $payload)->assertUnprocessable();
    }

    private function schedule(): HorarioSemanal
    {
        return HorarioSemanal::create(['semana_id' => '2026-W45', 'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-08',
            'plantilla_diseno' => [['dia' => 'miercoles', 'fecha' => '2026-11-04', 'turnos_requeridos' => [
                ['turno_id' => 'test', 'rol_necesario' => 'repartidor', 'hora_inicio' => '09:00', 'hora_fin' => '12:00']]]],
            'resultados' => [['turno_id' => 'test', 'empleado_id' => $this->employee->id]],
            'empleados_snapshot' => [['id' => $this->employee->id, 'rol' => 'repartidor', 'vacaciones' => ['2000-01-01']]],
            'roles_publicados' => ['repartidor'], 'generado_por' => $this->admin->id, 'estado' => 'published', 'vigente' => true]);
    }
}
