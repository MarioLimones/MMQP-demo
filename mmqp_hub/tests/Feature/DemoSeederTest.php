<?php

namespace Tests\Feature;

use App\Models\ConsumoDocument;
use App\Models\ConsumoRecord;
use App\Models\ConsumoSheet;
use App\Models\CuadreCaja;
use App\Models\Fichaje;
use App\Models\HorarioSemanal;
use App\Models\PlantillaHorario;
use App\Models\SolicitudAusencia;
use App\Models\User;
use App\Services\ConsumoHistory;
use App\Services\EmployeeAvailability;
use App\Support\ArqueoDocument;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStorage;
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->originalStorage = storage_path();
        $this->temporaryStorage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mmqp-demo-test-'.Str::uuid();
        File::ensureDirectoryExists($this->temporaryStorage.'/app');
        $this->app->useStoragePath($this->temporaryStorage);
        Storage::fake('local');
        Storage::fake('public');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 12:00', 'Europe/Madrid'));
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-02 12:00', 'Europe/Madrid'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    public function test_fresh_install_has_only_synthetic_profiles_and_complete_examples(): void
    {
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('users', 14);
        foreach (User::all() as $user) {
            $this->assertSame('Demo', $user->apellidos);
            $this->assertStringEndsWith('@mmqp.example', $user->email);
            $this->assertNull($user->telefono);
            $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $user->password));
        }
        $this->assertDatabaseCount('plantilla_horarios', 1);
        $this->assertDatabaseCount('horarios_semanales', 3);
        $this->assertDatabaseCount('solicitud_ausencias', 4);
        $this->assertDatabaseCount('festivos', 1);
        $this->assertDatabaseCount('consumo_documents', 1);
        $this->assertDatabaseCount('consumo_records', 1);
        $this->assertDatabaseCount('cuadre_cajas', 2);
        $this->assertDatabaseCount('cuadre_revisions', 2);
        $this->assertGreaterThan(0, Fichaje::count());
        $this->assertSame(1, Fichaje::whereNull('salida')->count());
        $this->assertSame('2026-11-02', DB::table('mmqp_demo_state')->where('key', 'reference_date')->value('value'));
        foreach (PlantillaHorario::sole()->turnos as $shift) {
            $this->assertNull($shift['empleado_id']);
        }
        foreach (CuadreCaja::all() as $cash) {
            $this->assertSame('0.00', $cash->descuadre);
            $this->assertSame('Demo', $cash->responsible->apellidos);
            $this->assertSame(1, $cash->revisions->count());
        }
        $record = ConsumoRecord::sole();
        $snapshot = app(ConsumoHistory::class)->viewSnapshot($record);
        $this->assertSame('10.000', $snapshot['suppliers'][0]['products'][0]['consumption']['amount']);
        $this->assertStringContainsString('10 UD', $snapshot['pages'][0]['grid_html']);
        $this->assertStringContainsString('CONSUMOS DEMO', ConsumoDocument::sole()->payload['pages'][0]['grid_html']);
        $this->assertCount(2, ConsumoDocument::sole()->payload['suppliers']);
        $this->assertCount(8, collect(ConsumoDocument::sole()->payload['suppliers'])->flatMap(fn ($supplier) => $supplier['products']));
        $this->assertStringContainsString('Consumido · UD', ConsumoDocument::sole()->payload['pages'][0]['grid_html']);

        $arqueo = app(ArqueoDocument::class)->read(storage_path('app/arqueos/2026-11-02.json'));
        $this->assertSame('125,00', $arqueo['values']['5-3']);
        $this->assertStringContainsString('ARQUEO DEMO', $arqueo['html']);
        $this->assertStringContainsString('EQUIPO Y ENTREGAS', $arqueo['html']);
        $this->assertStringContainsString('RESUMEN DEL TURNO', $arqueo['html']);
    }

    public function test_schedules_reference_only_available_employees_and_keep_draft_unpublished(): void
    {
        $this->seed(DemoSeeder::class);
        $this->assertSame(2, HorarioSemanal::vigentes()->count());
        $draft = HorarioSemanal::where('estado', 'borrador')->sole();
        $this->assertSame('2026-W46', $draft->semana_id);
        $this->assertFalse($draft->vigente);
        $this->assertSame([], $draft->roles_publicados);
        foreach (HorarioSemanal::all() as $schedule) {
            $ids = [];
            foreach ($schedule->plantilla_diseno as $day) {
                $seen = [];
                foreach ($day['turnos_requeridos'] as $shift) {
                    $user = User::findOrFail($shift['empleado_id']);
                    $this->assertTrue($user->isEmployee());
                    $this->assertTrue(app(EmployeeAvailability::class)->isAvailable($user, $day['fecha'], $shift['hora_inicio'] < '16:00' ? 'mañana' : 'tarde'));
                    $this->assertNotContains($user->id, $seen);
                    $seen[] = $user->id;
                    $ids[] = $shift['turno_id'];
                }
            }
            $this->assertSame($ids, array_column($schedule->resultados, 'turno_id'));
        }
        $employee = User::where('email', 'repartidora04@mmqp.example')->sole();
        $this->assertContains('2026-11-16', $employee->vacaciones);
        $this->assertSame('pendiente', SolicitudAusencia::where('user_id', User::where('email', 'empleado@mmqp.example')->sole()->id)->sole()->estado);
    }

    public function test_rerunning_the_seeder_preserves_visitors_changes(): void
    {
        $this->seed(DemoSeeder::class);
        $employee = User::where('email', 'empleado@mmqp.example')->sole();
        $employee->update(['nombre' => 'Nombre de prueba editado']);
        $sheet = ConsumoSheet::sole();
        $sheet->update(['title' => 'Ficha modificada en demo']);
        $template = storage_path('app/arqueo_template.json');
        File::put($template, '{"html":"<tr><td>Cambio de prueba</td></tr>"}');
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('users', 14);
        $this->assertDatabaseCount('consumo_records', 1);
        $this->assertSame('Nombre de prueba editado', $employee->fresh()->nombre);
        $this->assertSame('Ficha modificada en demo', $sheet->fresh()->title);
        $this->assertStringContainsString('Cambio de prueba', File::get($template));
    }

    public function test_examples_open_in_design_mode_and_keep_printable_geometry(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@mmqp.example')->sole();
        $response = $this->actingAs($admin)->get('/admin/arqueo?modo=editar')->assertOk()
            ->assertViewHas('dailyAvailable', true)->assertViewHas('workflowMode', 'design');
        $html = $response->viewData('templateData')['html'];
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8"><table>'.$html.'</table>');
        $rows = $dom->getElementsByTagName('tr');
        $this->assertCount(15, $rows);
        $this->assertEquals(100, array_sum(json_decode($rows[0]->getAttribute('data-arqueo-columns'), true)));
        $this->assertEquals(276, array_sum(json_decode($rows[0]->getAttribute('data-arqueo-rows'), true)));
        foreach ($rows as $row) {
            $width = 0;
            foreach ($row->getElementsByTagName('td') as $cell) { $width += (int) ($cell->getAttribute('colspan') ?: 1); }
            $this->assertSame(6, $width);
        }
        $this->postJson('/admin/arqueo/template', ['template' => ['html' => $html]])->assertOk()->assertJsonPath('success', true);
        $saved = app(ArqueoDocument::class)->read(storage_path('app/arqueo_template.json'));
        $this->assertStringContainsString('data-arqueo-columns', $saved['html']);
        $document = ConsumoDocument::sole();
        foreach ($document->payload['pages'] as $page) {
            @$dom->loadHTML('<?xml encoding="UTF-8"><table>'.$page['grid_html'].'</table>');
            foreach ($dom->getElementsByTagName('tr') as $row) {
                if (! $row->getElementsByTagName('td')->length) { continue; }
                $width = 0;
                foreach ($row->getElementsByTagName('td') as $cell) {
                    $width += (int) $cell->getAttribute('colspan');
                    $this->assertSame('3', $cell->getAttribute('rowspan'));
                }
                $this->assertSame(24, $width);
            }
        }
        $this->get('/admin/consumos/documento')->assertOk()->assertSee('CONSUMOS DEMO');
    }

    public function test_template_refresh_keeps_visitor_changes_and_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $document = ConsumoDocument::sole();
        $document->update(['title' => 'Mi plantilla de prueba', 'version' => 2]);
        $template = storage_path('app/arqueo_template.json');
        $changed = '<tr><td><div class="cell-content">Mi diseño de prueba</div></td></tr>';
        File::put($template, json_encode(['html' => $changed]));
        $this->artisan('demo:plantillas')->assertSuccessful();
        $this->artisan('demo:plantillas')->assertSuccessful();
        $this->assertSame('Mi plantilla de prueba', $document->fresh()->title);
        $this->assertSame($changed, json_decode(File::get($template), true)['html']);
        $this->assertDatabaseCount('consumo_records', 1);
        $this->assertDatabaseCount('users', 14);
    }

    public function test_template_refresh_refuses_unmarked_installations(): void
    {
        $this->artisan('demo:plantillas')->assertFailed();
        $this->assertDatabaseCount('consumo_documents', 0);
        $this->assertFalse(File::exists(storage_path('app/arqueo_template.json')));
    }

    public function test_nonempty_unmarked_database_is_never_overwritten_or_mixed(): void
    {
        $existing = User::factory()->create(['nombre' => 'Existing local fixture']);
        try {
            $this->seed(DemoSeeder::class);
            $this->fail('Unmarked nonempty databases must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('base de datos vacía', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $existing->id, 'nombre' => 'Existing local fixture']);
        $this->assertDatabaseCount('mmqp_demo_state', 0);
        $this->assertFalse(File::exists(storage_path('app/arqueo_template.json')));
    }

    public function test_demo_seed_is_explicitly_disabled_outside_demo_config(): void
    {
        config(['demo.enabled' => false]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_MODE=true');
        $this->seed(DemoSeeder::class);
    }

    public function test_real_login_and_core_views_load_using_seeded_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->post('/login', ['email' => 'admin@mmqp.example', 'password' => DemoSeeder::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs(User::where('email', 'admin@mmqp.example')->sole());
        foreach (['/dashboard', '/admin/empleados', '/admin/horarios', '/admin/horarios/historial',
            '/admin/vacaciones', '/admin/fichajes', '/admin/consumos', '/admin/consumos/historial',
            '/admin/cuadre/historial', '/admin/arqueo?modo=rellenar&fecha=2026-11-02'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/fichajes?fecha=2026-11-02')->assertOk()->assertViewHas('empleadosDict', function (array $employees) {
            $expected = User::where('email', 'empleado@mmqp.example')->sole();
            $this->assertSame([['inicio' => '12:00', 'fin' => '16:00']], $employees[$expected->id]['turnos_teoricos']);
            $this->assertCount(1, $employees[$expected->id]['fichajes_reales']);
            $clocking = $employees[$expected->id]['fichajes_reales'][0];
            $this->assertSame('2026-11-02 11:30:00', $clocking->entrada_local);
            $this->assertNull($clocking->salida_local);

            return true;
        });
        $this->get('/admin/consumos/historial/'.ConsumoRecord::sole()->id)->assertOk()->assertSee('CONSUMOS DEMO');
        $this->actingAs(User::where('email', 'empleado@mmqp.example')->sole());
        $this->get('/dashboard')->assertOk();
        $this->get('/dashboard/calendario-global')->assertOk();
        $this->get('/admin/empleados')->assertForbidden();
    }
}
