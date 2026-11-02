<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\User;
use App\Services\ScheduleTransfer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']);
        $this->actingAs($this->admin);
    }

    private function schedule(array $changes = [], int $employeeId = 77): HorarioSemanal
    {
        $assigned = ['turno_id' => 'assigned-1', '_idLocal' => 'assigned-1', 'puesto_num' => 3,
            'rol_necesario' => 'encargado', 'hora_inicio' => '20:00', 'hora_fin' => '02:30',
            'duracion_horas' => 6.5, 'empleado_id' => $employeeId, 'empleado_nombre' => 'Persona original'];
        $historical = array_replace($assigned, ['turno_id' => 'historical-2', '_idLocal' => 'historical-2',
            'puesto_num' => 4, 'empleado_id' => 'hist_existing_person', 'empleado_nombre' => 'Persona documental']);
        $unassigned = array_replace($assigned, ['turno_id' => 'unassigned-3', '_idLocal' => 'unassigned-3',
            'puesto_num' => 5, 'empleado_id' => null, 'empleado_nombre' => null]);

        return HorarioSemanal::unguarded(fn () => HorarioSemanal::create(array_replace([
            'semana_id' => '2026-W41', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-11',
            'plantilla_diseno' => [['dia' => 'lunes', 'fecha' => '2026-10-05', 'notas' => 'Nota guardada: cierre tardío.',
                'turnos_requeridos' => [$assigned, $historical, $unassigned]]],
            'resultados' => [
                ['turno_id' => 'assigned-1', 'empleado_id' => (string) $employeeId, 'nombre' => 'Persona original',
                    'dia' => 'lunes', 'fecha' => '2026-10-05', 'rol' => 'encargado',
                    'hora_inicio' => '20:00', 'hora_fin' => '02:30', 'duracion_horas' => 6.5],
                ['turno_id' => 'historical-2', 'empleado_id' => 'hist_existing_person', 'nombre' => 'Persona documental',
                    'dia' => 'lunes', 'fecha' => '2026-10-05', 'rol' => 'encargado',
                    'hora_inicio' => '20:00', 'hora_fin' => '02:30', 'duracion_horas' => 6.5],
            ],
            'empleados_snapshot' => [
                ['id' => $employeeId, 'nombre' => 'Persona original', 'rol' => 'encargado', 'orden_visual' => 2,
                    'en_baja' => false, 'baja_motivo' => null, 'vacaciones' => ['2026-09-01'],
                    'fechas_libres_solicitadas' => [], 'dias_bloqueados_recurrentes' => [],
                    'dias_disponibles' => [], 'horas_mensuales' => 120],
                ['id' => 'hist_existing_person', 'nombre' => 'Persona documental', 'rol' => 'encargado'],
            ],
            'turnos_cubiertos' => 2, 'turnos_totales' => 3,
            'estado' => 'published', 'vigente' => true, 'roles_publicados' => ['encargado'],
            'version' => 4, 'generado_por' => $this->admin->id,
            'created_at' => '2026-09-30 12:34:56', 'published_at' => '2026-10-01 14:20:00',
            'last_save_key' => 'internal-save-key', 'last_save_hash' => str_repeat('b', 64),
        ], $changes)))->refresh();
    }

    private function envelope(array $rows): array
    {
        return ['format' => 'mmqp.schedule-backup', 'version' => 1,
            'exported_at' => '2026-10-20T10:00:00+00:00', 'schedules' => $rows];
    }

    private function upload(mixed $input): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('horarios.json',
            json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function reverseObjectKeys(array $input): array
    {
        $result = array_map(fn ($value) => is_array($value) ? $this->reverseObjectKeys($value) : $value, $input);

        return array_is_list($result) ? $result : array_reverse($result, true);
    }

    public function test_json_downloads_contain_all_saved_content_and_single_export_matches_the_batch(): void
    {
        $first = $this->schedule();
        $second = $this->schedule(['semana_id' => '2026-W42', 'estado' => 'borrador', 'vigente' => false,
            'draft_slot' => 1, 'published_at' => null]);
        $response = $this->get(route('admin.horarios.exportar'))->assertOk();
        $this->assertStringStartsWith('application/json', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $json = $response->streamedContent();
        $batch = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('mmqp.schedule-backup', $batch['format']);
        $this->assertSame(1, $batch['version']);
        $this->assertNotEmpty($batch['exported_at']);
        $this->assertCount(2, $batch['schedules']);
        $this->assertEqualsCanonicalizing([$first->semana_id, $second->semana_id], array_column($batch['schedules'], 'semana_id'));
        $single = json_decode($this->get(route('admin.horarios.exportar', $first->id))
            ->assertOk()->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $single['schedules']);
        $row = $single['schedules'][0];
        $this->assertContains($row, $batch['schedules']);
        $this->assertSame($first->plantilla_diseno, $row['plantilla_diseno']);
        $this->assertSame($first->resultados, $row['resultados']);
        $this->assertSame($first->empleados_snapshot, $row['empleados_snapshot']);
        $this->assertSame(4, $row['version']);
        foreach (['id', 'generado_por', 'generador', 'last_save_key', 'last_save_hash', 'draft_slot', 'source_publicacion_id'] as $field) {
            $this->assertArrayNotHasKey($field, $row);
        }
        $this->assertStringNotContainsString('internal-save-key', $json);
        $this->assertStringNotContainsString($this->admin->getRawOriginal('password'), $json);
        $this->assertStringNotContainsString($this->admin->remember_token, $json);
    }

    public function test_downloaded_json_can_be_uploaded_on_another_database_without_binding_local_employee_ids(): void
    {
        $sourceEmployee = User::factory()->create(['id' => 77, 'nombre' => 'Persona original', 'rol' => 'encargado']);
        $source = $this->schedule([], $sourceEmployee->id);
        $beforeSnapshot = $source->empleados_snapshot;
        $beforeLayout = $source->plantilla_diseno;
        $beforeCreatedAt = $source->created_at->toDateTimeString();
        $beforePublishedAt = $source->published_at->toDateTimeString();
        $json = $this->get(route('admin.horarios.exportar', $source->id))->assertOk()->streamedContent();
        $source->delete();
        $sourceEmployee->delete();

        $destinationAdmin = User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']);
        User::factory()->create(['id' => 77, 'nombre' => 'Otra persona del servidor', 'rol' => 'auxiliar',
            'en_baja' => true, 'baja_motivo' => 'Situación actual del servidor']);
        User::factory()->create(['nombre' => 'Persona original', 'rol' => 'encargado']);
        $usersBefore = User::orderBy('id')->get()->map->getRawOriginal()->all();
        $this->actingAs($destinationAdmin)->post(route('admin.horarios.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('horarios.json', $json),
        ])->assertRedirect(route('admin.horarios.historial'));

        $imported = HorarioSemanal::sole();
        $transferredId = $imported->empleados_snapshot[0]['id'];
        $this->assertStringStartsWith('hist_transfer_', $transferredId);
        $this->assertSame($transferredId, $imported->plantilla_diseno[0]['turnos_requeridos'][0]['empleado_id']);
        $this->assertSame($transferredId, $imported->resultados[0]['empleado_id']);
        $this->assertSame('hist_existing_person', $imported->empleados_snapshot[1]['id']);
        $this->assertSame('hist_existing_person', $imported->resultados[1]['empleado_id']);
        $beforeSnapshot[0]['id'] = $transferredId;
        $beforeLayout[0]['turnos_requeridos'][0]['empleado_id'] = $transferredId;
        $this->assertSame($beforeSnapshot, $imported->empleados_snapshot);
        $this->assertSame($beforeLayout, $imported->plantilla_diseno);
        $this->assertSame('historico', $imported->estado);
        $this->assertFalse($imported->vigente);
        $this->assertNull($imported->draft_slot);
        $this->assertSame([], $imported->roles_publicados);
        $this->assertSame($destinationAdmin->id, $imported->generado_por);
        $this->assertSame(4, $imported->version);
        $this->assertSame($beforeCreatedAt, $imported->created_at->toDateTimeString());
        $this->assertSame($beforePublishedAt, $imported->published_at->toDateTimeString());
        $this->assertSame(2, (int) $imported->turnos_cubiertos);
        $this->assertSame(3, (int) $imported->turnos_totales);
        $this->assertSame(0, HorarioSemanal::vigentes()->count());
        $this->assertSame($usersBefore, User::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame(0, DB::table('fichajes')->count());

        $this->get(route('admin.horarios.resultado', $imported->id))->assertOk()
            ->assertViewHas('es_borrador', false)
            ->assertViewHas('data', function (array $data) use ($beforeSnapshot): bool {
                $shifts = $data['plantilla'][0]['turnos_requeridos'];

                return $data['estado'] === 'historico' && $data['empleados'] === $beforeSnapshot
                    && count($data['resultados']) === 2 && count($shifts) === 3
                    && $shifts[2]['empleado_id'] === null;
            });
    }

    public function test_retry_single_batch_and_reordered_object_keys_are_idempotent_but_changed_content_is_preserved(): void
    {
        $first = $this->schedule();
        $second = $this->schedule(['semana_id' => '2026-W42']);
        $batch = json_decode($this->get(route('admin.horarios.exportar'))->assertOk()->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $single = json_decode($this->get(route('admin.horarios.exportar', $first->id))->assertOk()->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $first->delete();
        $second->delete();
        $service = app(ScheduleTransfer::class);
        $this->assertSame(['imported' => 2, 'skipped' => 0], $service->import($batch, $this->admin->id));
        $before = HorarioSemanal::orderBy('id')->get()->map->getRawOriginal()->all();
        $batch['exported_at'] = '2026-10-21T10:00:00+00:00';
        $batch['schedules'] = array_reverse($batch['schedules']);
        $this->assertSame(['imported' => 0, 'skipped' => 2], $service->import($this->reverseObjectKeys($batch), $this->admin->id));
        $this->assertSame(['imported' => 0, 'skipped' => 1], $service->import($single, $this->admin->id));
        $reexported = json_decode($this->get(route('admin.horarios.exportar'))->assertOk()->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['imported' => 0, 'skipped' => 2], $service->import($reexported, $this->admin->id));
        $this->assertSame($before, HorarioSemanal::orderBy('id')->get()->map->getRawOriginal()->all());

        $single['schedules'][0]['plantilla_diseno'][0]['notas'] = 'Corrección que debe conservar otra versión.';
        $this->assertSame(['imported' => 1, 'skipped' => 0], $service->import($single, $this->admin->id));
        $this->assertSame(3, HorarioSemanal::count());
        $this->assertSame(3, HorarioSemanal::whereNotNull('transfer_hash')->distinct()->count('transfer_hash'));
    }

    public function test_older_results_grouped_by_day_are_kept_and_employee_ids_are_remapped_in_the_group(): void
    {
        $source = $this->schedule();
        $row = app(ScheduleTransfer::class)->exportRow($source);
        $row['resultados'] = [['dia' => 'lunes', 'fecha' => '2026-10-05', 'turnos_requeridos' => $row['resultados']]];
        $source->delete();
        $this->post(route('admin.horarios.importar'), ['archivo' => $this->upload($this->envelope([$row]))])
            ->assertRedirect(route('admin.horarios.historial'));
        $imported = HorarioSemanal::sole();
        $this->assertCount(1, $imported->resultados);
        $assignments = $imported->resultados[0]['turnos_requeridos'];
        $this->assertCount(2, $assignments);
        $this->assertSame($imported->empleados_snapshot[0]['id'], $assignments[0]['empleado_id']);
        $this->assertSame('hist_existing_person', $assignments[1]['empleado_id']);
        $this->assertSame('02:30', $assignments[0]['hora_fin']);
    }

    public function test_legacy_designs_keyed_by_day_are_normalized_without_losing_assignments(): void
    {
        $source = $this->schedule();
        $row = app(ScheduleTransfer::class)->exportRow($source);
        $day = $row['plantilla_diseno'][0];
        unset($day['dia']);
        $day['turnos_requeridos'][0]['id'] = $day['turnos_requeridos'][0]['turno_id'];
        unset($day['turnos_requeridos'][0]['turno_id']);
        $row['plantilla_diseno'] = ['lunes' => $day];
        $source->delete();
        $this->post(route('admin.horarios.importar'), ['archivo' => $this->upload($this->envelope([$row]))])
            ->assertRedirect(route('admin.horarios.historial'));
        $imported = HorarioSemanal::sole();
        $this->assertTrue(array_is_list($imported->plantilla_diseno));
        $this->assertSame('lunes', $imported->plantilla_diseno[0]['dia']);
        $this->assertSame('assigned-1', $imported->plantilla_diseno[0]['turnos_requeridos'][0]['turno_id']);
        $this->assertSame($imported->resultados[0]['empleado_id'], $imported->plantilla_diseno[0]['turnos_requeridos'][0]['empleado_id']);
        $this->assertSame('Nota guardada: cierre tardío.', $imported->plantilla_diseno[0]['notas']);
    }

    public function test_credentials_inside_old_snapshots_are_not_exported_or_restored(): void
    {
        $source = $this->schedule();
        $row = app(ScheduleTransfer::class)->exportRow($source);
        $row['empleados_snapshot'][0]['password'] = 'legacy-password-secret';
        $row['empleados_snapshot'][0]['remember_token'] = 'legacy-remember-secret';
        $row['empleados_snapshot'][0]['metadata'] = ['access_token' => 'nested-access-secret', 'label' => 'Conservar'];
        $oldSnapshot = $row['empleados_snapshot'];
        $source->delete();
        $source = $this->schedule(['empleados_snapshot' => $oldSnapshot]);
        $json = $this->get(route('admin.horarios.exportar', $source->id))->assertOk()->streamedContent();
        foreach (['legacy-password-secret', 'legacy-remember-secret', 'nested-access-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertStringContainsString('Conservar', $json);
        $source->delete();
        app(ScheduleTransfer::class)->import($this->envelope([$row]), $this->admin->id);
        $employee = HorarioSemanal::sole()->empleados_snapshot[0];
        $this->assertArrayNotHasKey('password', $employee);
        $this->assertArrayNotHasKey('remember_token', $employee);
        $this->assertSame(['label' => 'Conservar'], $employee['metadata']);
    }

    public function test_import_keeps_existing_publication_and_draft_unchanged_and_never_publishes_imported_rows(): void
    {
        $publication = $this->schedule();
        $draft = $this->schedule(['estado' => 'borrador', 'vigente' => false, 'draft_slot' => 1, 'published_at' => null]);
        $before = [$publication->getRawOriginal(), $draft->getRawOriginal()];
        $row = app(ScheduleTransfer::class)->exportRow($publication);
        $row['estado'] = 'published';
        $row['vigente'] = true;
        $row['draft_slot'] = 1;
        $row['generado_por'] = 999999;
        $row['roles_publicados'] = ['encargado'];
        $this->post(route('admin.horarios.importar'), ['archivo' => $this->upload($this->envelope([$row]))])
            ->assertRedirect(route('admin.horarios.historial'));
        $imported = HorarioSemanal::where('estado', 'historico')->sole();
        $this->assertSame($before, [$publication->fresh()->getRawOriginal(), $draft->fresh()->getRawOriginal()]);
        $this->assertSame($publication->id, HorarioSemanal::vigentes()->sole()->id);
        $this->assertFalse($imported->vigente);
        $this->assertNull($imported->draft_slot);
        $this->assertNull($imported->source_publicacion_id);
        $this->assertSame([], $imported->roles_publicados);
        $this->assertSame($this->admin->id, $imported->generado_por);
    }

    public function test_malformed_backup_or_nested_arrays_fail_without_importing_the_valid_first_row(): void
    {
        $source = $this->schedule();
        $valid = app(ScheduleTransfer::class)->exportRow($source);
        $source->delete();
        $base = $this->envelope([$valid]);
        $badDay = $valid;
        $badDay['plantilla_diseno'] = ['invalid day'];
        $badShift = $valid;
        $badShift['plantilla_diseno'][0]['turnos_requeridos'] = 'invalid shifts';
        $badResult = $valid;
        $badResult['resultados'] = ['invalid result'];
        $badGroupedResult = $valid;
        $badGroupedResult['resultados'] = [['dia' => 'lunes', 'turnos_requeridos' => 'invalid grouped shifts']];
        $badGroupedShift = $valid;
        $badGroupedShift['resultados'] = [['dia' => 'lunes', 'turnos_requeridos' => ['invalid grouped shift']]];
        $badEmployee = $valid;
        $badEmployee['empleados_snapshot'] = ['invalid employee'];
        $objectResults = $valid;
        $objectResults['resultados'] = ['unexpected-key' => $valid['resultados'][0]];
        $objectEmployees = $valid;
        $objectEmployees['empleados_snapshot'] = ['unexpected-key' => $valid['empleados_snapshot'][0]];
        $badRole = $valid;
        $badRole['empleados_snapshot'][0]['rol'] = ['encargado'];
        $badSurname = $valid;
        $badSurname['empleados_snapshot'][0]['apellidos'] = ['invalid surname'];
        $missingTime = $valid;
        unset($missingTime['plantilla_diseno'][0]['turnos_requeridos'][0]['hora_inicio']);
        $nullTime = $valid;
        $nullTime['plantilla_diseno'][0]['turnos_requeridos'][0]['hora_fin'] = null;
        foreach ([
            array_replace($base, ['format' => 'unrelated-backup']),
            array_replace($base, ['version' => 2]),
            array_replace($base, ['schedules' => 'invalid schedules']),
            $this->envelope([$valid, []]),
            $this->envelope([$valid, $badDay]),
            $this->envelope([$valid, $badShift]),
            $this->envelope([$valid, $badResult]),
            $this->envelope([$valid, $badGroupedResult]),
            $this->envelope([$valid, $badGroupedShift]),
            $this->envelope([$valid, $badEmployee]),
            $this->envelope([$valid, $objectResults]),
            $this->envelope([$valid, $objectEmployees]),
            $this->envelope([$valid, $badRole]),
            $this->envelope([$valid, $badSurname]),
            $this->envelope([$valid, $missingTime]),
            $this->envelope([$valid, $nullTime]),
        ] as $invalid) {
            $this->post(route('admin.horarios.importar'), ['archivo' => $this->upload($invalid)], ['Accept' => 'application/json'])
                ->assertUnprocessable();
            $this->assertSame(0, HorarioSemanal::count());
        }
        $this->post(route('admin.horarios.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('horarios.json', '{not-json'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->postJson(route('admin.horarios.importar'), [])->assertUnprocessable();
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_database_failure_rolls_back_the_complete_import_batch(): void
    {
        $first = $this->schedule();
        $second = $this->schedule(['semana_id' => '2026-W42']);
        $service = app(ScheduleTransfer::class);
        $rows = [$service->exportRow($first), $service->exportRow($second)];
        $first->delete();
        $second->delete();
        DB::unprepared("CREATE TRIGGER reject_second_transfer BEFORE INSERT ON horarios_semanales "
            ."WHEN NEW.semana_id = '2026-W42' BEGIN SELECT RAISE(ABORT, 'simulated transfer failure'); END");
        $failed = false;
        try {
            $service->import($this->envelope($rows), $this->admin->id);
        } catch (QueryException) {
            $failed = true;
        }
        $this->assertTrue($failed, 'The database trigger must interrupt the second insertion.');
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_history_orders_saved_dates_newest_first_within_each_month(): void
    {
        $old = $this->schedule(['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19',
            'created_at' => '2026-10-12 14:13:00']);
        $recent = $this->schedule(['created_at' => '2026-10-22 22:43:00']);
        $latest = $this->schedule(['created_at' => '2026-10-22 22:44:00']);
        $tie = $this->schedule(['created_at' => '2026-10-22 22:44:00']);

        $this->get(route('admin.horarios.historial'))->assertOk()
            ->assertViewHas('horariosAgrupados', function ($groups) use ($old, $recent, $latest, $tie) {
                $this->assertSame([$tie->id, $latest->id, $recent->id, $old->id],
                    $groups->get('2026')->get('10')->pluck('id')->all());
                return true;
            });
    }

    public function test_empty_history_keeps_import_form_available_and_saved_rows_have_export_links(): void
    {
        $this->get(route('admin.horarios.historial'))->assertOk()
            ->assertSee(route('admin.horarios.importar'), false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="archivo"', false);
        $schedule = $this->schedule();
        $this->get(route('admin.horarios.historial'))->assertOk()
            ->assertSee(route('admin.horarios.exportar'), false)
            ->assertSee(route('admin.horarios.exportar', $schedule->id), false);
    }

    public function test_only_authenticated_admins_can_export_or_import_and_unknown_schedule_returns_404(): void
    {
        $schedule = $this->schedule();
        $input = $this->envelope([app(ScheduleTransfer::class)->exportRow($schedule)]);
        auth()->forgetGuards();
        $this->get(route('admin.horarios.exportar'))->assertRedirect(route('login'));
        $this->get(route('admin.horarios.exportar', $schedule->id))->assertRedirect(route('login'));
        $this->post(route('admin.horarios.importar'), ['archivo' => $this->upload($input)])
            ->assertRedirect(route('login'));
        $employee = User::factory()->create(['es_admin' => false, 'activo' => true, 'rol' => 'encargado']);
        $this->actingAs($employee)->get(route('admin.horarios.exportar'))->assertForbidden();
        $this->get(route('admin.horarios.exportar', $schedule->id))->assertForbidden();
        $this->post(route('admin.horarios.importar'), ['archivo' => $this->upload($input)])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.horarios.exportar', 999999))->assertNotFound();
        $this->assertSame(1, HorarioSemanal::count());
    }
}
