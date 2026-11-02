<?php

namespace Tests\Feature;

use App\Models\CuadreCaja;
use App\Models\SolicitudAusencia;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CashHistoryAndRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_history_without_records_has_an_empty_month(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/cuadre/historial?month=2026-10')
            ->assertOk()
            ->assertViewHas('historial', fn ($records) => $records->isEmpty())
            ->assertSeeText('No hay cuadres de caja registrados en este mes.');
    }

    public function test_cash_history_preserves_month_boundaries_order_relations_and_amounts(): void
    {
        $employee = User::factory()->create(['nombre' => 'Encargada de prueba']);
        $opening = $this->cash('2026-10-31', 'apertura', ['created_by' => $employee->id]);
        $middle = $this->cash('2026-10-31', 'intermedio', ['venta_parcial' => '105.25']);
        $closing = $this->cash('2026-10-31', 'cierre', [
            'total_calculado' => '420.75', 'ingreso_real' => '120.75', 'descuadre' => '-2.50',
            'monedas' => ['2' => 3], 'billetes' => ['50' => 4],
            'monedas_ingreso' => ['2' => 1], 'billetes_ingreso' => ['50' => 2],
            'blisters' => ['total' => 10], 'nota_blanca' => '5.50',
        ]);
        $first = $this->cash('2026-10-01', 'cierre');
        $this->cash('2026-09-30', 'cierre');
        $this->cash('2026-11-01', 'cierre');

        DB::enableQueryLog();
        $response = $this->actingAs($this->admin())->get('/admin/cuadre/historial?month=2026-10');
        $response->assertOk()->assertSeeText('Encargada de prueba')
            ->assertSeeText('Desconocido')->assertSeeText('420.75 €')->assertSeeText('-2.50 €');
        $records = $response->viewData('historial');
        $this->assertSame([$closing->id, $middle->id, $opening->id, $first->id], $records->modelKeys());
        $this->assertSame([3, 1], $records->groupBy(fn ($record) => $record->fecha->toDateString())->map->count()->values()->all());
        $this->assertSame('420.75', $records->first()->total_calculado);
        $this->assertSame(['2' => 3], $records->first()->monedas);
        $this->assertSame('120.75', $records->first()->ingreso_real);
        $this->assertSame('300.00', $records->first()->fondo_fijo);
        $this->assertNull($records->last()->created_by);
        $this->assertNull($records->last()->ingreso_real);
        $this->assertTrue($records->every(fn ($record) => $record->relationLoaded('user')));
        $query = collect(DB::getQueryLog())->first(fn ($query) => str_contains($query['query'], 'cuadre_cajas'));
        $this->assertSame(['2026-10-01', '2026-11-01', 'cierre', 'intermedio', 'apertura'], $query['bindings']);
    }

    public function test_unrecognised_cash_shift_keeps_the_previous_field_zero_priority(): void
    {
        $closing = $this->cash('2026-10-10', 'cierre');
        $legacy = $this->cash('2026-10-10', 'antiguo');
        $response = $this->actingAs($this->admin())->get('/admin/cuadre/historial?month=2026-10');
        $response->assertOk();
        $this->assertSame([$legacy->id, $closing->id], $response->viewData('historial')->modelKeys());
    }

    public function test_month_filter_accepts_date_only_and_timestamp_values_at_the_boundaries(): void
    {
        $first = DB::table('cuadre_cajas')->insertGetId(['fecha' => '2026-10-01', 'turno' => 'apertura']);
        $last = DB::table('cuadre_cajas')->insertGetId(['fecha' => '2026-10-31 23:59:59', 'turno' => 'cierre']);
        DB::table('cuadre_cajas')->insert(['fecha' => '2026-11-01 00:00:00', 'turno' => 'apertura']);
        $response = $this->actingAs($this->admin())->get('/admin/cuadre/historial?month=2026-10')->assertOk();
        $this->assertSame([$last, $first], $response->viewData('historial')->modelKeys());
        $this->assertSame('2026-10-31', $response->viewData('historial')->first()->fecha->toDateString());
    }

    #[DataProvider('monthBoundaries')]
    public function test_month_filter_does_not_overflow_on_day_31(string $month, string $lastDay): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(23, 59));
        $firstDate = $month.'-01';
        $first = $this->cash($firstDate, 'apertura');
        $last = $this->cash($lastDay, 'cierre');
        $this->cash(\Carbon\Carbon::parse($firstDate)->subDay()->toDateString(), 'cierre');
        $this->cash(\Carbon\Carbon::parse($lastDay)->addDay()->toDateString(), 'apertura');

        $response = $this->actingAs($this->admin())->get('/admin/cuadre/historial?month='.$month);
        $response->assertOk()->assertViewHas('month', $month);
        $this->assertSame($firstDate, $response->viewData('startDate')->toDateString());
        $this->assertSame([$last->id, $first->id], $response->viewData('historial')->modelKeys());
    }

    public static function monthBoundaries(): array
    {
        return [
            'leap February' => ['2024-02', '2024-02-29'],
            'February' => ['2026-02', '2026-02-28'],
            'thirty days' => ['2026-04', '2026-04-30'],
            'year end' => ['2026-12', '2026-12-31'],
        ];
    }

    #[DataProvider('invalidMonths')]
    public function test_invalid_month_falls_back_to_the_current_month(array $parameters): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31));
        $record = $this->cash('2026-10-01', 'apertura');
        $this->cash('2026-11-01', 'apertura');
        $response = $this->actingAs($this->admin())->get('/admin/cuadre/historial?'.http_build_query($parameters));
        $response->assertOk()->assertViewHas('month', '2026-10');
        $this->assertSame([$record->id], $response->viewData('historial')->modelKeys());
    }

    public static function invalidMonths(): array
    {
        return [[['month' => 'invalid']], [['month' => '2026-13']], [['month' => '2026-00']], [['month' => ['2026-10']]]];
    }

    public function test_requests_without_records_have_an_empty_inbox(): void
    {
        $this->actingAs($this->admin())->get('/admin/solicitudes')
            ->assertOk()->assertSeeText('El buzón está vacío')
            ->assertViewHas('solicitudes', fn ($records) => $records->total() === 0);
    }

    public function test_requests_with_records_prioritise_pending_then_date_and_keep_optional_fields(): void
    {
        $employee = User::factory()->create(['nombre' => 'Empleado de prueba']);
        $approved = $this->requestFor($employee, '2026-09-30', ['estado' => 'aprobada', 'razon_admin' => 'Concedido']);
        $latePending = $this->requestFor($employee, '2026-11-01', ['fecha_fin' => '2026-11-03', 'tipo' => 'vacaciones']);
        $rejected = $this->requestFor($employee, '2026-10-01', ['estado' => 'rechazada']);
        $earlyPending = $this->requestFor($employee, '2026-10-01');
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin())->get('/admin/solicitudes');
        $response->assertOk()->assertSeeText('Empleado de prueba')->assertSeeText('01/10/2026');
        $records = $response->viewData('solicitudes');
        $this->assertSame([$earlyPending->id, $latePending->id, $approved->id, $rejected->id], $records->getCollection()->modelKeys());
        $this->assertSame(4, $records->total());
        $this->assertSame(20, $records->perPage());
        $this->assertNull($records[0]->fecha_fin);
        $this->assertNull($records[0]->motivo_empleado);
        $this->assertSame('2026-11-03', $records[1]->fecha_fin->toDateString());
        $this->assertSame('Concedido', $records[2]->razon_admin);
        $this->assertTrue($records->getCollection()->every(fn ($record) => $record->relationLoaded('user')));
        $queries = collect(DB::getQueryLog());
        $query = $queries->first(fn ($query) => str_contains($query['query'], 'CASE'));
        $this->assertContains('pendiente', $query['bindings']);
        // Rendering includes the sidebar's pending count and the eager-loaded employees.
        $this->assertTrue($queries->contains(fn ($query) => str_contains($query['query'], 'count(*)') && in_array('pendiente', $query['bindings'], true)));
    }

    public function test_request_pagination_has_no_duplicates_even_with_equal_dates(): void
    {
        $employee = User::factory()->create();
        $resolved = $this->requestFor($employee, '2026-01-01', ['estado' => 'aprobada']);
        $pending = collect();
        for ($i = 0; $i < 22; $i++) {
            $pending->push($this->requestFor($employee, '2026-10-10', ['motivo_empleado' => 'Petición '.$i]));
        }
        $this->actingAs($this->admin());
        $first = $this->get('/admin/solicitudes')->assertOk()->viewData('solicitudes');
        $second = $this->get('/admin/solicitudes?page=2')->assertOk()->viewData('solicitudes');
        $this->assertSame(23, $first->total());
        $this->assertSame(2, $first->lastPage());
        $this->assertSame(2, $second->currentPage());
        $this->assertSame($pending->take(20)->pluck('id')->all(), $first->getCollection()->modelKeys());
        $this->assertSame([$pending[20]->id, $pending[21]->id, $resolved->id], $second->getCollection()->modelKeys());
    }

    public function test_calendar_filters_rejected_requests_and_preserves_inclusive_date_ranges(): void
    {
        $employee = User::factory()->create();
        $single = $this->requestFor($employee, '2026-12-31');
        $range = $this->requestFor($employee, '2026-12-31', ['fecha_fin' => '2027-01-02', 'estado' => 'aprobada', 'tipo' => 'vacaciones']);
        $this->requestFor($employee, '2026-12-30', ['estado' => 'rechazada']);

        $response = $this->actingAs($this->admin())->get('/admin/vacaciones')->assertOk();
        $events = collect($response->viewData('eventos'))->keyBy('id');
        $this->assertCount(2, $events);
        $this->assertSame('2026-12-31', $events[$single->id]['start']);
        $this->assertArrayNotHasKey('end', $events[$single->id]);
        $this->assertNull($events[$single->id]['extendedProps']['motivo']);
        $this->assertSame('2027-01-03', $events[$range->id]['end']);
        $this->assertSame(['2026-12-31', '2027-01-01', '2027-01-02'], $range->getDatesRange());
    }

    public function test_employee_only_sees_their_requests_and_administrators_see_all(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceRequest = $this->requestFor($alice, '2026-10-10', ['motivo_empleado' => 'Solo Alice']);
        $bobRequest = $this->requestFor($bob, '2026-10-11', ['motivo_empleado' => 'Solo Bob']);
        foreach ([[$alice, $aliceRequest, 'Solo Bob'], [$bob, $bobRequest, 'Solo Alice']] as [$user, $record, $hidden]) {
            $response = $this->actingAs($user)->get('/perfil/disponibilidad')->assertOk()->assertDontSeeText($hidden);
            $this->assertSame([$record->id], $response->viewData('solicitudes')->modelKeys());
        }
        $this->actingAs($this->admin())->get('/admin/solicitudes')->assertOk()->assertSeeText('Solo Alice')->assertSeeText('Solo Bob');
    }

    #[DataProvider('administrativePages')]
    public function test_administrative_pages_require_authentication_and_admin_access(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['es_admin' => false]))->get($path)->assertForbidden();
    }

    public static function administrativePages(): array
    {
        return [['/admin/cuadre/historial'], ['/admin/solicitudes'], ['/admin/vacaciones']];
    }

    #[DataProvider('failingPages')]
    public function test_database_errors_are_reported_and_never_rendered_as_empty_data(string $path, string $table): void
    {
        $this->actingAs($this->admin());
        config(['app.debug' => false]);
        Log::spy();
        // Only the isolated test connection is altered. DDL is restored even on MySQL.
        Schema::rename($table, $table.'_unavailable');
        try {
            $this->get($path)->assertStatus(500)
                ->assertSeeText('No se pudo cargar la página')
                ->assertDontSeeText('El buzón está vacío')
                ->assertDontSeeText('No hay cuadres de caja registrados');
            Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
                $exception = $context['exception'] ?? null;
                while ($exception) {
                    if ($exception instanceof QueryException) {
                        return true;
                    }
                    $exception = $exception->getPrevious();
                }
                return false;
            })->atLeast()->once();
        } finally {
            Schema::rename($table.'_unavailable', $table);
        }
    }

    public static function failingPages(): array
    {
        return [
            ['/admin/cuadre/historial', 'cuadre_cajas'],
            ['/admin/solicitudes', 'solicitud_ausencias'],
            // A secondary query made by the navigation must also remain observable.
            ['/admin/cuadre/historial', 'solicitud_ausencias'],
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['es_admin' => true, 'rol' => 'oficina']);
    }

    private function cash(string $date, string $shift, array $attributes = []): CuadreCaja
    {
        return CuadreCaja::create(array_merge(['fecha' => $date, 'turno' => $shift, 'total_calculado' => '300.00'], $attributes));
    }

    private function requestFor(User $user, string $date, array $attributes = []): SolicitudAusencia
    {
        return SolicitudAusencia::create(array_merge(['user_id' => $user->id, 'fecha' => $date, 'estado' => 'pendiente'], $attributes));
    }
}
