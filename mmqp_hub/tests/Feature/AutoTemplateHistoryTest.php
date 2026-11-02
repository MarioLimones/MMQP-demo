<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Services\AutoTemplateHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutoTemplateHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function shift(array $changes = []): array
    {
        return array_replace(['turno_id' => 'private-shift', 'rol_necesario' => 'encargado',
            'hora_inicio' => '19:00', 'hora_fin' => '02:30', 'empleado_id' => 'private-id',
            'empleado_nombre' => 'Nombre privado', 'notas_dia' => 'Nota privada'], $changes);
    }

    private function day(string $date = '2026-10-19', array $shifts = []): array
    {
        return ['dia' => 'lunes', 'fecha' => $date, 'turnos_requeridos' => $shifts];
    }

    private function schedule(array $changes = []): HorarioSemanal
    {
        return HorarioSemanal::create(array_replace(['semana_id' => '2026-W43',
            'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25',
            'estado' => 'published', 'vigente' => true, 'roles_publicados' => ['encargado'],
            'plantilla_diseno' => [$this->day('2026-10-19', [$this->shift()])],
            'resultados' => [], 'empleados_snapshot' => []], $changes));
    }

    public function test_uses_only_active_published_roles_and_preserves_repeated_overnight_demand(): void
    {
        $this->schedule(['roles_publicados' => ['encargado', 'repartidor'], 'plantilla_diseno' => [
            $this->day('2026-10-19', [$this->shift(), $this->shift(['empleado_id' => 'another-private-id']),
                $this->shift(['rol_necesario' => 'auxiliar'])]),
            $this->day('2026-10-25', [$this->shift(['rol_necesario' => 'repartidor', 'hora_inicio' => '12:00', 'hora_fin' => '16:00'])]),
        ]]);
        foreach ([['estado' => 'borrador'], ['estado' => 'historico'], ['vigente' => false],
            ['roles_publicados' => []], ['estado' => 'guardado']] as $changes) {
            $this->schedule($changes);
        }
        $before = DB::table('horarios_semanales')->orderBy('id')->get()->toJson();
        $monday = Carbon::parse('2026-10-26 13:47:00');

        $references = app(AutoTemplateHistory::class)->referencias($monday);

        $this->assertSame([
            '2026-10-19' => ['encargado' => [
                ['hora_inicio' => '19:00', 'hora_fin' => '02:30'],
                ['hora_inicio' => '19:00', 'hora_fin' => '02:30'],
            ], 'repartidor' => []],
            '2026-10-25' => ['encargado' => [], 'repartidor' => [
                ['hora_inicio' => '12:00', 'hora_fin' => '16:00'],
            ]],
        ], $references);
        foreach (['private', 'Nombre', 'Nota', 'empleado', 'turno_id'] as $field) {
            $this->assertStringNotContainsString($field, json_encode($references));
        }
        $this->assertSame('2026-10-26 13:47:00', $monday->toDateTimeString());
        $this->assertSame($before, DB::table('horarios_semanales')->orderBy('id')->get()->toJson());
    }

    public function test_limits_references_to_previous_week_and_rejects_missing_or_invalid_dates(): void
    {
        $this->schedule(['plantilla_diseno' => [
            $this->day('2026-10-18', [$this->shift()]),
            $this->day('2026-10-19', [$this->shift()]),
            $this->day('2026-10-26', [$this->shift()]),
            $this->day('19 oct', [$this->shift()]),
            $this->day('2026-02-30', [$this->shift()]),
            ['dia' => 'martes', 'turnos_requeridos' => [$this->shift()]],
        ]]);
        $this->schedule(['fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01',
            'plantilla_diseno' => [$this->day('2026-10-19', [$this->shift()])]]);
        $this->schedule(['fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-18',
            'plantilla_diseno' => [$this->day('2026-10-19', [$this->shift()])]]);

        $references = app(AutoTemplateHistory::class)->referencias(Carbon::parse('2026-10-26'));

        $this->assertSame(['2026-10-19' => ['encargado' => [
            ['hora_inicio' => '19:00', 'hora_fin' => '02:30'],
        ]]], $references);
    }

    public function test_latest_active_publication_replaces_a_role_and_keeps_explicit_zero(): void
    {
        $this->schedule(['roles_publicados' => ['encargado', 'repartidor'], 'plantilla_diseno' => [
            $this->day('2026-10-19', [$this->shift(), $this->shift(['rol_necesario' => 'repartidor'])]),
        ]]);
        $this->schedule(['roles_publicados' => ['encargado'], 'plantilla_diseno' => [$this->day()]]);

        $references = app(AutoTemplateHistory::class)->referencias(Carbon::parse('2026-10-26'));

        $this->assertSame(['2026-10-19' => ['encargado' => [], 'repartidor' => [
            ['hora_inicio' => '19:00', 'hora_fin' => '02:30'],
        ]]], $references);
    }

    public function test_invalid_shift_hours_do_not_enter_the_reference_map(): void
    {
        $this->schedule(['plantilla_diseno' => [$this->day('2026-10-19', [
            $this->shift(['hora_inicio' => '25:00']), $this->shift(['hora_fin' => null]),
            $this->shift(['hora_inicio' => '19:00', 'hora_fin' => '19:00']),
            $this->shift(['hora_inicio' => '9:00']),
        ])]]);

        $this->assertSame(['2026-10-19' => ['encargado' => []]],
            app(AutoTemplateHistory::class)->referencias(Carbon::parse('2026-10-26')));
    }
}
