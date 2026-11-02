<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\User;
use App\Services\HistoricalScheduleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalScheduleImportTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function row(string $source = 'source-1', string $date = '2023-01-01', array $changes = []): array
    {
        return array_replace(['source_id' => $source, 'fecha' => $date, 'empleado' => 'Persona histórica',
            'empleado_documento' => 'documento-ficticio-1', 'hora_inicio' => '20:00', 'hora_fin' => '02:30',
            'puesto' => 'repartidor 50 horas', 'puesto_documento' => 'repartidor 50 horas',
            'archivo_origen' => 'Registro 2023.PDF', 'archivo_sha256' => str_repeat('a', 64), 'paginas_origen' => [1],
            'fichaje_entrada' => 'No fichado', 'fichaje_salida' => 'No fichado', 'notas_dia' => ''], $changes);
    }

    private function incident(string $source = 'incident-1', string $date = '2023-01-01', array $changes = []): array
    {
        return array_replace(['source_id' => $source, 'fecha' => $date, 'empleado' => 'Persona histórica',
            'empleado_documento' => 'documento-ficticio-1', 'archivo_origen' => 'Registro 2023.PDF',
            'archivo_sha256' => str_repeat('a', 64), 'paginas_origen' => [2],
            'notas_dia' => 'Entrada programada sin salida en el documento.',
            'datos_origen' => ['codigo' => 'sin_pareja', 'hora' => '17:00', 'tipo' => 'Entrada']], $changes);
    }

    private function fixtureJson(mixed $input): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mmqp-historical-');
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function import(mixed $input, array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('horarios:importar-historico', ['ruta' => $this->fixtureJson($input)] + $options);
    }

    private function shifts(HorarioSemanal $schedule): array
    {
        return collect($schedule->plantilla_diseno)->flatMap(fn ($day) => $day['turnos_requeridos'])->values()->all();
    }

    public function test_all_four_sources_are_grouped_by_exact_iso_week_and_keep_overnight_and_24_hour_intervals(): void
    {
        $rows = [$this->row(),
            $this->row('source-2024', '2024-12-30', ['archivo_origen' => 'Registro 2024.PDF', 'hora_inicio' => '00:00', 'hora_fin' => '24:00']),
            $this->row('source-2025', '2025-01-01', ['archivo_origen' => 'Registro 2025.PDF']),
            $this->row('source-2026', '2026-10-01', ['archivo_origen' => 'Registro 2026.PDF', 'puesto' => 'sin_puesto', 'puesto_documento' => ''])];
        $this->import($rows)->expectsOutput('Turnos nuevos: 4')->expectsOutput('Semanas nuevas: 3')->assertSuccessful();

        $this->assertSame(['2022-W52', '2025-W01', '2026-W40'], HorarioSemanal::orderBy('semana_id')->pluck('semana_id')->all());
        $edge = HorarioSemanal::where('semana_id', '2022-W52')->firstOrFail();
        $this->assertSame('2022-12-26', $edge->fecha_inicio);
        $this->assertSame('2023-01-01', $edge->fecha_fin);
        $this->assertSame('domingo', $edge->plantilla_diseno[6]['dia']);
        $this->assertSame(6.5, $this->shifts($edge)[0]['duracion_horas']);
        $this->assertSame(390, $this->shifts($edge)[0]['duracion_minutos']);
        $sameWeek = HorarioSemanal::where('semana_id', '2025-W01')->firstOrFail();
        $this->assertSame('2024-12-30', $sameWeek->fecha_inicio);
        $this->assertSame(2, $sameWeek->turnos_totales);
        $this->assertEquals(24, $this->shifts($sameWeek)[0]['duracion_horas']);
        $allShifts = HorarioSemanal::all()->flatMap(fn ($schedule) => $this->shifts($schedule));
        $this->assertEqualsCanonicalizing(array_column($rows, 'source_id'), $allShifts->pluck('source_id')->all());
        $this->assertCount(4, $allShifts->pluck('archivo_origen')->unique());
        foreach (HorarioSemanal::all() as $schedule) {
            $this->assertSame('historico', $schedule->estado);
            $this->assertFalse($schedule->vigente);
            $this->assertSame([], $schedule->roles_publicados);
            $this->assertNull($schedule->draft_slot);
            $this->assertNull($schedule->generado_por);
            $this->assertTrue($schedule->is_importado);
        }
        $this->assertSame(0, HorarioSemanal::vigentes()->count());
    }

    public function test_existing_publications_drafts_users_and_clock_records_are_preserved_and_retry_is_idempotent(): void
    {
        $user = User::factory()->create(['es_admin' => true]);
        $base = ['semana_id' => '2022-W52', 'fecha_inicio' => '2022-12-26', 'fecha_fin' => '2023-01-01',
            'plantilla_diseno' => [], 'resultados' => [], 'empleados_snapshot' => [], 'generado_por' => $user->id];
        $published = HorarioSemanal::create($base + ['estado' => 'published', 'vigente' => true, 'roles_publicados' => ['repartidor']]);
        $draft = HorarioSemanal::create($base + ['estado' => 'borrador', 'vigente' => false, 'draft_slot' => 1]);
        $before = [$published->fresh()->getRawOriginal(), $draft->fresh()->getRawOriginal()];
        $userBefore = $user->fresh()->getRawOriginal();
        $rows = [$this->row(), $this->row(), $this->row('split', '2023-01-01', ['hora_inicio' => '12:00', 'hora_fin' => '15:00'])];

        $this->import($rows, ['--generado-por' => $user->id])->expectsOutput('Duplicados idénticos en el JSON: 1')->assertSuccessful();
        $imported = HorarioSemanal::where('estado', 'historico')->firstOrFail();
        $this->assertSame($user->id, $imported->generado_por);
        $this->assertCount(2, $this->shifts($imported));
        $importedBefore = $imported->getRawOriginal();
        $this->import($rows)->expectsOutput('Registros ya importados: 2')->expectsOutput('Turnos nuevos: 0')->assertSuccessful();
        $this->assertSame(3, HorarioSemanal::count());
        $this->assertSame($before, [$published->fresh()->getRawOriginal(), $draft->fresh()->getRawOriginal()]);
        $this->assertSame($importedBefore, $imported->fresh()->getRawOriginal());
        $this->assertSame($userBefore, $user->fresh()->getRawOriginal());
        $this->assertSame(0, DB::table('fichajes')->count());
    }

    public function test_incidents_are_traceable_without_creating_invented_shifts(): void
    {
        $incident = $this->incident();
        $input = ['version' => 1, 'turnos' => [$this->row()], 'incidencias' => [$incident,
            $this->incident('incident-2', '2024-02-15', ['notas_dia' => 'Registro original incompleto: Salida sin pareja.'])]];
        $this->import($input)->expectsOutput('Turnos nuevos: 1')->expectsOutput('Incidencias nuevas: 2')->assertSuccessful();
        $edge = HorarioSemanal::where('semana_id', '2022-W52')->firstOrFail();
        $day = $edge->plantilla_diseno[6];
        $this->assertStringContainsString('Persona histórica: '.$incident['notas_dia'], $day['notas']);
        $stored = $day['incidencias_importacion'][0];
        $this->assertSame($incident['source_id'], $stored['source_id']);
        $this->assertSame($incident['datos_origen']['hora'], $stored['datos_origen']['hora']);
        $this->assertSame($incident['archivo_sha256'], $stored['archivo_sha256']);
        $incidentOnly = HorarioSemanal::where('semana_id', '2024-W07')->firstOrFail();
        $this->assertSame(0, $incidentOnly->turnos_totales);
        $this->assertSame([], $incidentOnly->resultados);
        $this->assertCount(1, $incidentOnly->empleados_snapshot);
        $this->assertTrue($incidentOnly->is_importado);
        $this->import($input)->expectsOutput('Registros ya importados: 3')->expectsOutput('Incidencias nuevas: 0')->assertSuccessful();
        $this->assertSame(2, HorarioSemanal::count());
    }

    public function test_dry_run_checks_all_rows_and_user_attribution_without_writes(): void
    {
        $this->import(['turnos' => [$this->row()], 'incidencias' => [$this->incident()]], ['--dry-run' => true])
            ->expectsOutput('Validación completada; no se ha escrito ningún horario.')->expectsOutput('Turnos nuevos: 1')->assertSuccessful();
        $this->assertSame(0, HorarioSemanal::count());
        $this->import([$this->row()], ['--generado-por' => '999999'])->assertFailed();
        $this->import([$this->row()], ['--generado-por' => '1.5'])->assertFailed();
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_documented_zero_minute_pairs_are_preserved_and_not_converted_to_24_hours(): void
    {
        $row = $this->row('zero', '2023-01-01', ['hora_inicio' => '21:00', 'hora_fin' => '21:00', 'duracion_minutos' => 0]);
        $this->import([$row])->assertSuccessful();
        $schedule = HorarioSemanal::firstOrFail();
        $this->assertSame(0, $this->shifts($schedule)[0]['duracion_minutos']);
        $this->assertEquals(0, $this->shifts($schedule)[0]['duracion_horas']);
        $this->assertSame(0, $schedule->resultados[0]['duracion_minutos']);
        $this->import([$row])->expectsOutput('Registros ya importados: 1')->assertSuccessful();
        $this->assertSame(1, HorarioSemanal::count());
    }

    public function test_invalid_dates_missing_hours_ambiguous_legacy_and_conflicting_sources_never_write_partial_data(): void
    {
        foreach ([['fecha' => '2023-02-29'], ['fecha' => 'lunes 1'], ['hora_inicio' => '24:00'],
            ['hora_fin' => '25:00'], ['hora_fin' => ''], ['hora_inicio' => '20:00', 'hora_fin' => '20:00'],
            ['paginas_origen' => [0]], ['puesto' => 'desconocido'], ['source_id' => '']] as $invalid) {
            $this->import([$this->row(), $this->row('invalid', '2024-01-01', $invalid)])->assertFailed();
            $this->assertSame(0, HorarioSemanal::count());
        }
        $this->import([['archivo_origen' => 'PHOTO-2024-01-01.jpg', 'fecha_o_dia' => 'lunes 1',
            'empleado' => 'Antiguo', 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'puesto' => 'auxiliar']])->assertFailed();
        $this->import([$this->row(), $this->row('source-1', '2023-01-01', ['hora_fin' => '03:00'])])->assertFailed();
        $this->import(['incidencias' => [$this->incident('incomplete', '2023-01-01', ['hora_inicio' => '12:00'])]])->assertFailed();
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_changed_imported_source_is_rejected_and_existing_history_is_immutable(): void
    {
        $this->import([$this->row()])->assertSuccessful();
        $before = HorarioSemanal::firstOrFail()->getRawOriginal();
        $this->import([$this->row('new-week', '2026-10-01'), $this->row('source-1', '2023-01-01', ['hora_fin' => '03:00'])])->assertFailed();
        $this->assertSame(1, HorarioSemanal::count());
        $this->assertSame($before, HorarioSemanal::firstOrFail()->getRawOriginal());
    }

    public function test_database_failure_rolls_back_every_week_in_the_batch(): void
    {
        DB::unprepared("CREATE TRIGGER reject_second_historical BEFORE INSERT ON horarios_semanales "
            ."WHEN NEW.semana_id = '2024-W01' BEGIN SELECT RAISE(ABORT, 'simulated import failure'); END");
        $this->import([$this->row(), $this->row('second', '2024-01-01')])->assertFailed();
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_same_name_with_different_document_identity_is_not_linked_or_merged(): void
    {
        $user = User::factory()->create(['nombre' => 'Persona histórica']);
        $this->import([$this->row(), $this->row('other-person', '2023-01-01', ['empleado_documento' => 'documento-ficticio-2'])])->assertSuccessful();
        $schedule = HorarioSemanal::firstOrFail();
        $this->assertCount(2, $schedule->empleados_snapshot);
        $ids = array_column($schedule->empleados_snapshot, 'id');
        $this->assertCount(2, array_unique($ids));
        $this->assertNotContains($user->id, $ids);
        $this->assertTrue(collect($ids)->every(fn ($id) => str_starts_with($id, 'hist_')));
    }

    public function test_historical_state_is_excluded_from_operational_scope_even_if_a_visibility_flag_changes(): void
    {
        $this->import([$this->row()])->assertSuccessful();
        HorarioSemanal::query()->update(['vigente' => true]);
        $this->assertSame(0, HorarioSemanal::vigentes()->count());
        $admin = User::factory()->create(['es_admin' => true]);
        $this->actingAs($admin)->get('/admin/horarios/historial')->assertOk()->assertSee('Histórico importado');
    }

    public function test_week_navigation_keeps_documental_history_separate_from_a_publication_and_draft_of_the_same_week(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $base = ['semana_id' => '2026-W41', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-11',
            'plantilla_diseno' => [], 'resultados' => [], 'empleados_snapshot' => [], 'generado_por' => $admin->id];
        $publication = HorarioSemanal::create($base + ['estado' => 'published', 'vigente' => true, 'roles_publicados' => ['repartidor']]);
        $this->import([$this->row('navigation-source', '2026-10-05')])->assertSuccessful();
        $history = HorarioSemanal::where('estado', 'historico')->firstOrFail();
        $this->actingAs($admin);
        $historyUrl = route('admin.horarios.resultado', $history->id);
        $publicationUrl = route('admin.horarios.resultado', $publication->id);
        $this->get($historyUrl.'?semana=2026-W41')->assertRedirect($historyUrl);
        $this->get($publicationUrl.'?semana=2026-W41')->assertRedirect($publicationUrl);

        $draft = HorarioSemanal::create($base + ['estado' => 'borrador', 'vigente' => false, 'draft_slot' => 1]);
        $this->get($historyUrl.'?semana=2026-W41')->assertRedirect($historyUrl);
        $this->get($publicationUrl.'?semana=2026-W41')->assertRedirect(route('admin.horarios.resultado', $draft->id));
    }

    public function test_documental_result_page_omits_import_warnings_without_assigning_an_invented_shift(): void
    {
        $admin = User::factory()->create(['es_admin' => true]);
        $incident = $this->incident('page-incident', '2026-10-05');
        $this->import(['turnos' => [$this->row('page-source', '2026-10-05')], 'incidencias' => [$incident]])->assertSuccessful();
        $history = HorarioSemanal::where('estado', 'historico')->firstOrFail();
        $before = $history->getRawOriginal();
        $response = $this->actingAs($admin)->get(route('admin.horarios.resultado', $history->id));
        $response->assertOk()->assertSee('Registro documental de consulta.')
            ->assertDontSee('Horario Importado (Modo de visualización)')
            ->assertDontSee('Datos del original que requieren revisión')
            ->assertDontSee('Incidencias del documento original')
            ->assertViewHas('es_borrador', false)
            ->assertViewHas('data', function (array $data) use ($incident): bool {
                $shifts = collect($data['plantilla'])->flatMap(fn ($day) => $day['turnos_requeridos']);
                $incidents = collect($data['plantilla'])->flatMap(fn ($day) => $day['incidencias_importacion']);

                return $data['estado'] === 'historico' && $data['is_importado'] && ! $data['vigente']
                    && $shifts->count() === 1 && count($data['resultados']) === 1 && $incidents->count() === 1
                    && $incidents->first()['source_id'] === $incident['source_id']
                    && ! $shifts->contains('source_id', $incident['source_id']);
            });
        $this->assertSame($before, $history->fresh()->getRawOriginal());
        $this->assertSame(1, $history->turnos_totales);
        $this->assertSame(0, DB::table('fichajes')->count());
    }

    public function test_json_failures_return_failure_without_database_changes(): void
    {
        $badJson = $this->fixtureJson([]);
        file_put_contents($badJson, '{not-json');
        $this->artisan('horarios:importar-historico', ['ruta' => $badJson])->assertFailed();
        $this->import([])->assertFailed();
        $this->import(['turnos' => 'invalid'])->assertFailed();
        $this->import(['turnos' => [$this->row()], 'errores_extraccion' => 1])->assertFailed();
        $this->artisan('horarios:importar-historico', ['ruta' => $badJson.'.missing'])->assertFailed();
        $this->assertSame(0, HorarioSemanal::count());
    }

    public function test_service_reports_new_only_when_existing_batch_is_extended_and_metadata_order_is_irrelevant(): void
    {
        $service = app(HistoricalScheduleImporter::class);
        $first = $this->row('metadata', '2023-01-01', ['datos_origen' => ['b' => 2, 'a' => ['y' => 1, 'x' => 0]]]);
        $service->import([$first]);
        $first['datos_origen'] = ['a' => ['x' => 0, 'y' => 1], 'b' => 2];
        $summary = $service->import([$first, $this->row('added', '2023-01-02')]);
        $this->assertSame(1, $summary['already_imported']);
        $this->assertSame(1, $summary['new_shifts']);
        $this->assertSame(2, HorarioSemanal::sum('turnos_totales'));
    }
}
