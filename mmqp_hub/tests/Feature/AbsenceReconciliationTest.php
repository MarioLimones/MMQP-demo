<?php

namespace Tests\Feature;

use App\Models\AvailabilityBlock;
use App\Models\LegacyAvailabilityImport;
use App\Models\SolicitudAusencia;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsenceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $options = []): array
    {
        $this->assertSame(0, Artisan::call('ausencias:reconciliar', $options + ['--json' => true]));

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function legacyUser(): User
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'vacaciones' => '["2026-11-03","2026-11-03","2026-11-09","bad-date"]',
            'fechas_libres_solicitadas' => '["2026-11-03","2026-11-10","2026-02-30"]',
        ]);

        return $user->fresh();
    }

    public function test_diagnostic_does_not_write_and_reports_missing_dates_and_ambiguities(): void
    {
        $user = $this->legacyUser();
        SolicitudAusencia::create(['user_id' => $user->id, 'fecha' => '2026-11-02', 'fecha_fin' => '2026-11-04',
            'tipo' => 'vacaciones', 'estado' => 'aprobada']);
        SolicitudAusencia::create(['user_id' => $user->id, 'fecha' => '2026-11-09', 'tipo' => 'vacaciones', 'estado' => 'rechazada']);
        $before = DB::table('users')->where('id', $user->id)->first();
        $report = $this->report();
        $this->assertSame('diagnostic', $report['mode']);
        $this->assertSame(1, $report['imports']);
        $this->assertSame(4, $report['blocks_created']);
        $this->assertSame(['2026-11-02', '2026-11-04'], $report['users'][0]['missing_vacation_dates']);
        $this->assertCount(2, $report['users'][0]['invalid_legacy_values']);
        $this->assertSame(0, LegacyAvailabilityImport::count());
        $this->assertSame(0, AvailabilityBlock::count());
        $this->assertEquals($before, DB::table('users')->where('id', $user->id)->first());
        $this->assertSame(['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-09', '2026-11-10'], $user->fresh()->fechas_libres_solicitadas);
        $this->assertSame(['2026-11-02', '2026-11-03', '2026-11-04'], $user->fresh()->vacaciones);
    }

    public function test_repair_is_idempotent_and_preserves_all_original_records(): void
    {
        $user = $this->legacyUser();
        $rejected = SolicitudAusencia::create(['user_id' => $user->id, 'fecha' => '2026-11-09', 'tipo' => 'vacaciones', 'estado' => 'rechazada']);
        $this->report(['--apply' => true]);
        $import = LegacyAvailabilityImport::first();
        $this->assertSame($user->getRawOriginal('vacaciones'), $import->vacaciones_original);
        $this->assertSame($user->getRawOriginal('fechas_libres_solicitadas'), $import->fechas_libres_original);
        $beforeUsers = DB::table('users')->get()->all();
        $beforeBlocks = AvailabilityBlock::all()->toArray();
        $second = $this->report(['--apply' => true]);
        $this->assertSame(0, $second['imports']);
        $this->assertSame(0, $second['blocks_created']);
        $this->assertEquals($beforeUsers, DB::table('users')->get()->all());
        $this->assertSame($beforeBlocks, AvailabilityBlock::all()->toArray());
        $this->assertSame('rechazada', $rejected->fresh()->estado);
        $this->assertSame(1, SolicitudAusencia::count());
        $this->assertSame([], $user->fresh()->vacaciones);
        $this->assertSame(['2026-11-03', '2026-11-09', '2026-11-10'], $user->fresh()->fechas_libres_solicitadas);
    }

    public function test_releasing_a_historical_cause_keeps_other_sources_and_active_absences(): void
    {
        $user = $this->legacyUser();
        $admin = User::factory()->create(['es_admin' => true]);
        $this->report(['--apply' => true]);
        $absence = app(AbsenceService::class)->create($admin, ['user_id' => $user->id, 'fecha' => '2026-11-03',
            'tipo' => 'baja', 'request_key' => (string) Str::uuid()]);
        foreach (AvailabilityBlock::where('fecha', '2026-11-03')->get() as $block) {
            $this->report(['--apply' => true, '--release-block' => $block->id, '--note' => 'Origen revisado: efecto antiguo de solicitud']);
            $this->assertContains('2026-11-03', $user->fresh()->fechas_libres_solicitadas);
        }
        app(AbsenceService::class)->delete($admin, $absence->id, 1);
        $this->assertSame(['2026-11-09', '2026-11-10'], $user->fresh()->fechas_libres_solicitadas);
        $this->report(['--apply' => true]);
        $this->assertSame(['2026-11-09', '2026-11-10'], $user->fresh()->fechas_libres_solicitadas);
        $this->assertSame(4, AvailabilityBlock::count());
    }

    public function test_manual_review_requires_a_reason_and_diagnostic_review_does_not_write(): void
    {
        $user = $this->legacyUser();
        $this->report(['--apply' => true]);
        $block = AvailabilityBlock::first();
        $this->assertSame(1, Artisan::call('ausencias:reconciliar', ['--apply' => true, '--release-block' => $block->id]));
        $report = $this->report(['--release-block' => $block->id, '--note' => 'Revisado']);
        $this->assertSame(1, $report['review_changes']);
        $this->assertSame('pending_review', $block->fresh()->status);
        $this->report(['--apply' => true, '--confirm-block' => $block->id, '--note' => 'Bloqueo independiente confirmado']);
        $this->assertSame('confirmed', $block->fresh()->status);
        $this->assertContains($block->fecha, $user->fresh()->fechas_libres_solicitadas);
    }

    public function test_invalid_historical_intervals_are_reported_without_losing_original_data(): void
    {
        $user = User::factory()->create();
        $absence = SolicitudAusencia::create(['user_id' => $user->id, 'fecha' => '2026-11-04', 'fecha_fin' => '2026-11-02',
            'tipo' => 'vacaciones', 'estado' => 'aprobada']);
        $report = $this->report(['--apply' => true]);
        $this->assertSame([$absence->id], $report['users'][0]['invalid_absences']);
        $this->assertSame([], $user->fresh()->vacaciones);
        $this->assertSame('2026-11-02', $absence->fresh()->fecha_fin->format('Y-m-d'));
        $this->assertSame(1, SolicitudAusencia::count());
    }

    public function test_uninterpretable_json_is_archived_and_not_converted_to_vacations(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['vacaciones' => '"legacy format"']);
        $report = $this->report(['--apply' => true]);
        $this->assertCount(1, $report['users'][0]['invalid_legacy_values']);
        $this->assertSame('"legacy format"', LegacyAvailabilityImport::first()->vacaciones_original);
        $this->assertSame([], $user->fresh()->vacaciones);
        $this->assertSame(0, AvailabilityBlock::count());
    }

    public function test_double_encoded_imports_are_recovered_without_changing_originals(): void
    {
        $user = User::factory()->create();
        $original = json_encode(json_encode(['2026-11-02']));
        DB::table('users')->where('id', $user->id)->update(['fechas_libres_solicitadas' => $original]);
        $this->assertSame(['2026-11-02'], $user->fresh()->fechas_libres_solicitadas);
        $report = $this->report(['--apply' => true]);
        $this->assertSame([], $report['users'][0]['invalid_legacy_values']);
        $this->assertSame($original, LegacyAvailabilityImport::first()->fechas_libres_original);
        $this->assertSame(1, AvailabilityBlock::count());
        $this->assertSame(0, $this->report(['--apply' => true])['blocks_created']);
    }
}
