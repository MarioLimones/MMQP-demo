<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_keeps_original_json_ids_relationships_and_visibility(): void
    {
        $migration = require database_path('migrations/2026_10_10_170000_separate_schedule_drafts_and_publications.php');
        $migration->down();
        $base = ['semana_id' => '2026-W41', 'fecha_inicio' => '5 oct', 'fecha_fin' => '11 oct',
            'plantilla_diseno' => '[{"dia":"lunes","turnos_requeridos":[{"turno_id":"old"}]}]',
            'resultados' => '[{"turno_id":"old","empleado_id":"hist_1"}]',
            'empleados_snapshot' => '[{"id":"hist_1","nombre":"Antiguo"}]',
            'generado_por' => null, 'created_at' => '2026-10-01 12:00:00', 'updated_at' => '2026-10-01 12:00:00'];
        $publishedId = DB::table('horarios_semanales')->insertGetId($base + ['roles_publicados' => '["encargado"]']);
        $ambiguousId = DB::table('horarios_semanales')->insertGetId($base + ['roles_publicados' => null]);
        $before = DB::table('horarios_semanales')->orderBy('id')->get()->keyBy('id');
        $migration->up();
        foreach ($before as $id => $row) {
            $after = (array) DB::table('horarios_semanales')->find($id);
            foreach ((array) $row as $column => $value) {
                $this->assertSame($value, $after[$column], 'Changed original column: '.$column);
            }
            $this->assertSame(1, $after['vigente']);
            $this->assertNull($after['draft_slot']);
        }
        $this->assertSame('published', DB::table('horarios_semanales')->find($publishedId)->estado);
        $this->assertSame('legacy', DB::table('horarios_semanales')->find($ambiguousId)->estado);
    }
}
