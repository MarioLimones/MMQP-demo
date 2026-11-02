<?php

namespace Tests\Feature;

use App\Models\CuadreCaja;
use App\Models\CuadreRevision;
use App\Models\User;
use App\Services\CuadreCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CuadreCajaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true, 'dni' => '87654321A']);
        $this->employee = User::factory()->create(['dni' => '01234567B']);
        $this->actingAs($this->admin);
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'fecha' => '2026-10-10', 'turno' => 'cierre', 'billetes' => ['100' => '4'], 'monedas' => [],
            'billetes_ingreso' => ['100' => '1'], 'monedas_ingreso' => [], 'blisters' => ['total' => '0.00'],
            'nota_blanca' => '0.00', 'fondo_fijo' => '300.00', 'dni_empleado' => '01234567',
            'expected_version' => 0, 'request_id' => (string) Str::uuid(), 'motivo' => null,
        ], $changes);
    }

    public function test_client_totals_and_audit_metadata_cannot_control_persisted_results(): void
    {
        $data = $this->payload(['total_calculado' => '0.01', 'ingreso_real' => '9999', 'descuadre' => '-900',
            'created_by' => $this->employee->id, 'actor_id' => $this->employee->id, 'version' => 900, 'recorded_at' => '1900-01-01']);
        $this->postJson(route('admin.cuadre.store'), $data)->assertOk()->assertJsonPath('saved.results.total_calculado', '400.00')
            ->assertJsonPath('saved.results.ingreso_real', '100.00')->assertJsonPath('saved.results.descuadre', '0.00');
        $cuadre = CuadreCaja::sole();
        $revision = CuadreRevision::sole();
        $this->assertSame('400.00', $cuadre->total_calculado);
        $this->assertSame($this->admin->id, $cuadre->created_by);
        $this->assertSame($this->admin->id, $revision->actor_id);
        $this->assertSame($this->employee->id, $cuadre->responsible_id);
        $this->assertSame(1, $revision->version);
        $this->assertTrue($revision->recorded_at->isToday());
        $this->assertSame('employee_declarations', $revision->snapshot['references']['source']);
    }

    #[DataProvider('formulaCases')]
    public function test_verified_formula_for_each_shift(array $changes, string $total, string $difference): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload($changes))->assertOk()
            ->assertJsonPath('saved.results.total_calculado', $total)->assertJsonPath('saved.results.descuadre', $difference);
    }

    public static function formulaCases(): array
    {
        return [
            'apertura balanced' => [['turno' => 'apertura', 'billetes' => ['100' => 3], 'billetes_ingreso' => []], '300.00', '0.00'],
            'cierre shortfall' => [['billetes' => ['100' => 3]], '300.00', '-100.00'],
            'cents exact' => [['monedas' => ['0.1' => 3, '0.2' => 1, '0.01' => 7]], '400.57', '0.57'],
            'vouchers and blisters stay' => [['blisters' => ['total' => '0.10'], 'nota_blanca' => '0.20'], '400.30', '0.30'],
            'intermedio balanced' => [['turno' => 'intermedio', 'billetes' => ['100' => 5], 'billetes_ingreso' => [],
                'gastos' => '10.01', 'cambios' => '2.00', 'repartos' => '3.10', 'pedidos_hora_exacta' => '4.00', 'otros' => '5.00', 'venta_parcial' => '224.11'], '524.11', '0.00'],
            'intermedio surplus' => [['turno' => 'intermedio', 'billetes_ingreso' => [], 'gastos' => '0', 'cambios' => '0',
                'repartos' => '0', 'pedidos_hora_exacta' => '0', 'otros' => '0', 'venta_parcial' => '50.05'], '400.00', '49.95'],
        ];
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_inputs_are_rejected_without_side_effects(array $changes, string $field): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload($changes))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('cuadre_cajas', 0);
        $this->assertDatabaseCount('cuadre_revisions', 0);
    }

    public static function invalidCases(): array
    {
        return [
            'fractional notes' => [['billetes' => ['100' => '1.5']], 'billetes'],
            'fractional coins' => [['monedas' => ['0.1' => 1.5]], 'monedas'],
            'negative units' => [['billetes' => ['100' => -1]], 'billetes'],
            'empty units' => [['monedas' => ['0.1' => '']], 'monedas'],
            'null units' => [['billetes' => ['100' => null]], 'billetes'],
            'boolean units' => [['billetes' => ['100' => true]], 'billetes'],
            'exponential units' => [['billetes' => ['100' => '1e2']], 'billetes'],
            'oversized units' => [['billetes' => ['100' => 1000000]], 'billetes'],
            'invalid notes' => [['billetes' => ['200' => 1]], 'billetes'],
            'invalid coins' => [['monedas' => ['0.03' => 1]], 'monedas'],
            'wrong array' => [['monedas' => '0'], 'monedas'],
            'deposit too large' => [['billetes_ingreso' => ['100' => 5]], 'billetes_ingreso'],
            'deposit invalid denomination' => [['monedas_ingreso' => ['0.03' => 1]], 'monedas_ingreso'],
            'negative money' => [['nota_blanca' => '-0.01'], 'nota_blanca'],
            'precision' => [['nota_blanca' => '0.001'], 'nota_blanca'],
            'NaN' => [['fondo_fijo' => 'NaN'], 'fondo_fijo'],
            'infinite' => [['fondo_fijo' => 'Infinity'], 'fondo_fijo'],
            'float JSON money' => [['nota_blanca' => 0.1], 'nota_blanca'],
            'empty money' => [['nota_blanca' => ''], 'nota_blanca'],
            'too large money' => [['fondo_fijo' => '100000000'], 'fondo_fijo'],
            'empty blisters' => [['blisters' => ['total' => '']], 'blisters.total'],
            'invalid blisters key' => [['blisters' => ['total' => '0', '2' => '10']], 'blisters'],
            'unsupported blister deposit' => [['blisters_ingreso' => ['total' => '1']], 'blisters_ingreso'],
            'impossible date' => [['fecha' => '2026-02-30'], 'fecha'],
            'date timestamp' => [['fecha' => '2026-10-10T12:00:00'], 'fecha'],
            'invalid shift' => [['turno' => 'noche'], 'turno'],
            'DNI prefix' => [['dni_empleado' => '0123'], 'dni_empleado'],
            'unknown employee' => [['dni_empleado' => '99999999'], 'dni_empleado'],
            'null optional declared field' => [['gastos' => null], 'gastos'],
            'unrelated shift field' => [['gastos' => '1'], 'gastos'],
            'missing intermediate fields' => [['turno' => 'intermedio', 'billetes_ingreso' => []], 'venta_parcial'],
            'intermediate deposit' => [['turno' => 'intermedio', 'gastos' => '0', 'cambios' => '0', 'repartos' => '0', 'pedidos_hora_exacta' => '0', 'otros' => '0', 'venta_parcial' => '0'], 'billetes_ingreso'],
            'computed overflow' => [['billetes' => ['100' => 999999], 'blisters' => ['total' => '100.00']], 'billetes'],
            'bad version' => [['expected_version' => -1], 'expected_version'],
            'bad idempotency id' => [['request_id' => 'not-a-uuid'], 'request_id'],
        ];
    }

    #[DataProvider('depositDenominations')]
    public function test_deposit_is_limited_to_units_of_its_own_denomination(string $kind, int|string $denomination, int $cents): void
    {
        $counts = ['billetes' => ['100' => '4'], 'monedas' => [], 'billetes_ingreso' => [], 'monedas_ingreso' => []];
        $counts[$kind][$denomination] = '1';
        if ($kind === 'billetes' && (string) $denomination === '100') {
            $counts['billetes']['50'] = '4';
        }
        // Other denominations cover the deposit's value, but cannot replace units of this row.
        $counts[$kind.'_ingreso'][$denomination] = '2';
        $this->postJson(route('admin.cuadre.store'), $this->payload($counts))
            ->assertUnprocessable()->assertJsonValidationErrors($kind.'_ingreso');
        $this->assertDatabaseCount('cuadre_cajas', 0);
        $this->assertDatabaseCount('cuadre_revisions', 0);

        $counts[$kind.'_ingreso'][$denomination] = '1';
        $this->postJson(route('admin.cuadre.store'), $this->payload($counts))
            ->assertOk()->assertJsonPath('saved.results.ingreso_real', CuadreCalculator::decimal($cents));
        $this->assertDatabaseCount('cuadre_cajas', 1);
        $this->assertDatabaseCount('cuadre_revisions', 1);
    }

    public static function depositDenominations(): array
    {
        $cases = [];
        foreach (['billetes' => CuadreCalculator::BILLETES, 'monedas' => CuadreCalculator::MONEDAS] as $kind => $denominations) {
            foreach ($denominations as $denomination => $cents) {
                $cases[$kind.' '.$denomination] = [$kind, $denomination, $cents];
            }
        }

        return $cases;
    }

    public function test_reducing_counted_units_below_the_deposit_does_not_change_a_saved_cuadre(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $before = (array) DB::table('cuadre_cajas')->sole();
        $snapshot = CuadreRevision::sole()->snapshot;

        $this->postJson(route('admin.cuadre.store'), $this->payload([
            'expected_version' => 1, 'billetes' => ['100' => '0', '50' => '4'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('billetes_ingreso');

        $this->assertSame($before, (array) DB::table('cuadre_cajas')->sole());
        $this->assertSame(1, CuadreCaja::sole()->version);
        $this->assertDatabaseCount('cuadre_revisions', 1);
        $this->assertSame($snapshot, CuadreRevision::sole()->snapshot);
    }

    public function test_html_deposit_validation_preserves_the_counted_and_deposit_draft(): void
    {
        $url = route('admin.cuadre', ['fecha' => '2026-10-10', 'turno' => 'cierre']);
        $data = $this->payload(['monedas' => ['0.1' => '1'], 'monedas_ingreso' => ['0.1' => '2']]);
        $this->from($url)->post(route('admin.cuadre.store'), $data)->assertRedirect($url)
            ->assertSessionHasErrors('monedas_ingreso')
            ->assertSessionHasInput('monedas', $data['monedas'])
            ->assertSessionHasInput('monedas_ingreso', $data['monedas_ingreso']);

        $html = $this->get($url)->assertOk()->assertSee('Las unidades al ingreso no pueden superar las contadas en caja.')->getContent();
        $this->assertMatchesRegularExpression('/<input\\b(?=[^>]*\\bname="monedas\\[0\\.1\\]")(?=[^>]*\\bvalue="1")[^>]*>/s', $html);
        $this->assertMatchesRegularExpression('/<input\\b(?=[^>]*\\bname="monedas_ingreso\\[0\\.1\\]")(?=[^>]*\\bvalue="2")[^>]*>/s', $html);
        $this->assertDatabaseCount('cuadre_cajas', 0);
        $this->assertDatabaseCount('cuadre_revisions', 0);
    }

    public function test_required_fields_cannot_be_omitted(): void
    {
        $payload = $this->payload();
        foreach (['fecha', 'turno', 'billetes', 'monedas', 'blisters', 'nota_blanca', 'fondo_fijo', 'dni_empleado', 'expected_version', 'request_id'] as $field) {
            $request = $payload;
            unset($request[$field]);
            $this->postJson(route('admin.cuadre.store'), $request)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_maximum_amount_remains_exact_in_storage_and_response(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload(['billetes' => ['100' => 999999], 'nota_blanca' => '99.99']))
            ->assertOk()->assertJsonPath('saved.results.total_calculado', '99999999.99');
        $this->assertSame('99999999.99', CuadreCaja::sole()->total_calculado);
        $this->assertSame(9999999999, CuadreCaja::sole()->calculation_snapshot['result_cents']['total_calculado']);
    }

    public function test_correction_keeps_complete_previous_state_and_authenticated_author(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $before = CuadreRevision::sole()->snapshot;
        $secondAdmin = User::factory()->create(['es_admin' => true]);
        $this->actingAs($secondAdmin)->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1, 'monedas' => ['0.01' => 3], 'motivo' => 'Recuento de céntimos']))
            ->assertOk()->assertJsonPath('saved.version', 2)->assertJsonPath('saved.results.descuadre', '0.03');
        $this->assertDatabaseCount('cuadre_cajas', 1);
        $this->assertDatabaseCount('cuadre_revisions', 2);
        $revision = CuadreRevision::where('version', 2)->sole();
        $this->assertSame($before, $revision->before_snapshot);
        $this->assertSame($secondAdmin->id, $revision->actor_id);
        $this->assertSame('Recuento de céntimos', $revision->motivo);
        $this->assertSame($this->admin->id, CuadreCaja::sole()->created_by);
    }

    public function test_retries_are_idempotent_even_after_a_later_correction(): void
    {
        $payload = $this->payload();
        $first = $this->postJson(route('admin.cuadre.store'), $payload)->assertOk()->json();
        $this->postJson(route('admin.cuadre.store'), $payload)->assertOk()->assertExactJson($first);
        $this->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1, 'nota_blanca' => '0.20']))->assertOk();
        $this->postJson(route('admin.cuadre.store'), $payload)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('cuadre_revisions', 2);
        $this->assertSame(2, CuadreCaja::sole()->version);
        $this->postJson(route('admin.cuadre.store'), array_replace($payload, ['nota_blanca' => '10.00']))->assertConflict();
        $this->employee->delete();
        $this->postJson(route('admin.cuadre.store'), $payload)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('cuadre_revisions', 2);
        $this->actingAs(User::factory()->create(['es_admin' => true]))->postJson(route('admin.cuadre.store'), $payload)->assertConflict();
    }

    public function test_two_editors_cannot_overwrite_a_stale_version(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $this->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 0]))->assertConflict()->assertJsonPath('current_version', 1);
        $this->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1, 'nota_blanca' => '1.00']))->assertOk();
        $this->actingAs(User::factory()->create(['es_admin' => true]))
            ->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1, 'nota_blanca' => '2.00']))->assertConflict()->assertJsonPath('current_version', 2);
        $this->assertSame('1.00', CuadreCaja::sole()->nota_blanca);
        $this->assertDatabaseCount('cuadre_revisions', 2);
    }

    public function test_audit_failure_rolls_back_creation_and_correction(): void
    {
        $event = 'eloquent.creating: '.CuadreRevision::class;
        $listener = fn () => throw new RuntimeException('Simulated audit write failure');
        Event::listen($event, $listener);
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertServerError();
        $this->assertDatabaseCount('cuadre_cajas', 0);
        Event::forget($event);
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $before = DB::table('cuadre_cajas')->first();
        Event::listen($event, $listener);
        try {
            $this->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1, 'nota_blanca' => '4.00']))->assertServerError();
            $this->assertEquals($before, DB::table('cuadre_cajas')->first());
            $this->assertDatabaseCount('cuadre_revisions', 1);
        } finally {
            Event::forget($event);
        }
    }

    public function test_monthly_summary_counts_each_current_closure_once(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $this->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1, 'billetes_ingreso' => ['100' => 2]]))->assertOk();
        $this->postJson(route('admin.cuadre.store'), $this->payload(['fecha' => '2026-10-11']))->assertOk();
        $this->postJson(route('admin.cuadre.store'), $this->payload(['turno' => 'apertura', 'billetes_ingreso' => []]))->assertOk();
        $this->getJson(route('admin.cuadre.historial', ['month' => '2026-10']))->assertOk()
            ->assertJsonPath('summary.cierres', 2)->assertJsonPath('summary.ingreso_calculado', '300.00')->assertJsonCount(3, 'cuadres');
    }

    public function test_direct_http_calls_respect_permissions_and_history_is_read_only(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $id = CuadreCaja::sole()->id;
        $this->actingAs($this->employee);
        foreach (['admin.cuadre', 'admin.cuadre.historial'] as $route) {
            $this->getJson(route($route))->assertForbidden();
        }
        $this->getJson(route('admin.cuadre.revisiones', $id))->assertForbidden();
        $this->postJson(route('admin.cuadre.store'), $this->payload(['expected_version' => 1]))->assertForbidden();
        $this->app['auth']->forgetGuards();
        foreach (['admin.cuadre', 'admin.cuadre.historial'] as $route) {
            $this->getJson(route($route))->assertUnauthorized();
        }
        $this->getJson(route('admin.cuadre.revisiones', $id))->assertUnauthorized();
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertUnauthorized();
        $this->actingAs($this->admin);
        $this->patchJson(route('admin.cuadre.revisiones', $id), [])->assertMethodNotAllowed();
        $this->deleteJson(route('admin.cuadre.revisiones', $id))->assertMethodNotAllowed();
        $this->assertDatabaseCount('cuadre_revisions', 1);
    }

    public function test_form_keeps_saved_version_without_confirmation_panel_and_history_remains_available(): void
    {
        $data = $this->payload(['monedas' => ['0.1' => 3]]);
        $this->post(route('admin.cuadre.store'), $data)->assertRedirect()->assertSessionHas('saved_cuadre.results.total_calculado', '400.30');
        $cuadre = CuadreCaja::sole();
        $this->get(route('admin.cuadre', ['fecha' => $data['fecha']]))->assertOk()->assertDontSee('id="saved-result"', false)
            ->assertDontSee('Resultado confirmado por el servidor')->assertDontSee('Consultar historial de modificaciones')
            ->assertSee('name="expected_version" value="1"', false);
        $this->get(route('admin.cuadre.revisiones', $cuadre))->assertOk()->assertSee('Autor autenticado:')
            ->assertSee($this->admin->nombre)->assertSee('Valores guardados')->assertSee('employee_declarations');
        $this->getJson(route('admin.cuadre.revisiones', $cuadre))->assertOk()->assertJsonPath('data.0.snapshot.results.total_calculado', '400.30');
        $this->get(route('admin.cuadre.historial', ['month' => '2026-10']))->assertOk()
            ->assertSee('100.00 €')->assertSee('Resumen del día')->assertDontSee('Ver versiones')
            ->assertDontSee('Ver desglose')->assertDontSee('Cuadres del día:');
    }

    public function test_html_conflict_preserves_draft_and_requires_explicit_reload(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $this->post(route('admin.cuadre.store'), $this->payload(['nota_blanca' => '2.37']))->assertConflict()
            ->assertSee('Tus datos siguen en el formulario')->assertSee('value="2.37"', false)->assertSee('descartar este borrador');
        $this->assertSame('0.00', CuadreCaja::sole()->nota_blanca);
    }

    public function test_html_conflict_keeps_its_draft_out_of_other_tabs_in_the_same_session(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload([
            'billetes' => ['100' => '2'], 'billetes_ingreso' => [],
        ]))->assertOk();
        $this->postJson(route('admin.cuadre.store'), $this->payload([
            'expected_version' => 1, 'billetes' => ['100' => '3'], 'billetes_ingreso' => [],
        ]))->assertOk()->assertJsonPath('saved.version', 2);

        $rejected = $this->payload([
            'expected_version' => 1, 'billetes' => ['100' => '4'], 'billetes_ingreso' => [],
            'blisters' => ['total' => '2.37'], 'nota_blanca' => '3.15', 'motivo' => 'Borrador de la otra pestaña',
        ]);
        $this->post(route('admin.cuadre.store'), $rejected)->assertConflict()
            ->assertSee('Tus datos siguen en el formulario')
            ->assertSee('name="billetes[100]" value="4"', false)
            ->assertSee('name="expected_version" value="1"', false)
            ->assertSee('name="request_id" value="'.$rejected['request_id'].'"', false)
            ->assertSee('value="2.37"', false)->assertSee('value="3.15"', false)
            ->assertSee('Borrador de la otra pestaña')
            ->assertSessionMissing('_old_input');

        // The next GET may be the winning tab: it must receive the committed state.
        $this->get(route('admin.cuadre', ['fecha' => $rejected['fecha'], 'turno' => 'cierre']))
            ->assertOk()->assertDontSee('id="cuadre-conflict"', false)
            ->assertSee('name="billetes[100]" value="3"', false)
            ->assertSee('name="expected_version" value="2"', false)
            ->assertDontSee('Borrador de la otra pestaña');
        $this->assertSame(3, CuadreCaja::sole()->calculation_snapshot['inputs']['billetes']['100']);
        $this->assertSame(2, CuadreCaja::sole()->version);
        $this->assertDatabaseCount('cuadre_revisions', 2);
        $this->assertDatabaseMissing('cuadre_revisions', ['request_id' => $rejected['request_id']]);
    }

    public function test_database_constraints_and_model_protect_revision_integrity(): void
    {
        $this->postJson(route('admin.cuadre.store'), $this->payload())->assertOk();
        $revision = CuadreRevision::sole();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $revision->update(['motivo' => 'tamper']) : $revision->delete();
                $this->fail('Revision mutation should be blocked');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('inmutables', $error->getMessage());
            }
        }
        $this->assertDatabaseCount('cuadre_revisions', 1);
        try {
            DB::table('cuadre_cajas')->delete();
            $this->fail('Audited closures must not be deleted');
        } catch (QueryException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
    }

    public function test_migration_preserves_original_imported_values_and_diagnoses_inconsistency(): void
    {
        // Recreate the pre-change schema within the test transaction, then execute the real migration.
        Schema::drop('cuadre_revisions');
        Schema::table('cuadre_cajas', function ($table) {
            $table->dropForeign(['responsible_id']);
            $table->dropColumn(['responsible_id', 'version', 'calculation_snapshot']);
        });
        $id = DB::table('cuadre_cajas')->insertGetId(['fecha' => '2026-09-11', 'turno' => 'cierre',
            'billetes' => json_encode(['100' => 4]), 'monedas' => json_encode(['0.1' => 3]),
            'billetes_ingreso' => json_encode(['100' => 1]), 'total_calculado' => '999.99', 'ingreso_real' => '100.00',
            'descuadre' => '25.43', 'created_by' => $this->employee->id, 'created_at' => '2026-09-11 20:00:00', 'updated_at' => '2026-09-12 10:00:00']);
        $before = (array) DB::table('cuadre_cajas')->where('id', $id)->first();
        $migration = require database_path('migrations/2026_10_10_170000_add_cuadre_revisions.php');
        $migration->up();
        $current = (array) DB::table('cuadre_cajas')->where('id', $id)->first();
        $this->assertSame($before, array_intersect_key($current, $before));
        $revision = CuadreRevision::sole();
        $this->assertSame('legacy_import', $revision->origin);
        $this->assertNull($revision->actor_id);
        $this->assertNull($revision->actor_snapshot);
        $this->assertSame($before, $revision->snapshot['original_attributes']);
        $this->assertSame('inconsistent', $revision->snapshot['assessment']['status']);
        $this->assertSame('400.30', $revision->snapshot['assessment']['differences']['total_calculado']['recalculated']);
        // Retrying a partially completed migration must not fabricate another import.
        $migration->up();
        $this->assertDatabaseCount('cuadre_revisions', 1);
        $this->getJson(route('admin.cuadre.historial', ['month' => '2026-09']))->assertOk()
            ->assertJsonPath('summary.ingreso_total', '100.00')->assertJsonPath('summary.ingreso_legacy', '100.00');
        $this->postJson(route('admin.cuadre.store'), $this->payload(['fecha' => '2026-09-11']))->assertOk();
        $new = CuadreRevision::where('version', 1)->sole();
        $this->assertSame($revision->snapshot, $new->before_snapshot);
        $this->assertSame(1, CuadreCaja::find($id)->version);
    }
}
