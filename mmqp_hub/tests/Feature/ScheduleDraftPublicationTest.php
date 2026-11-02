<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\PlantillaHorario;
use App\Models\User;
use App\Services\ScheduleDrafts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleDraftPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private HorarioSemanal $publication;

    private string $week = '2026-W41';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']);
        $this->employee = User::factory()->create(['es_admin' => false, 'activo' => true, 'rol' => 'encargado']);
        $this->publication = HorarioSemanal::create($this->content() + [
            'semana_id' => $this->week, 'estado' => 'published', 'roles_publicados' => ['encargado'],
            'vigente' => true, 'generado_por' => $this->admin->id,
        ]);
        $this->publication->refresh();
        $this->actingAs($this->admin);
    }

    private function content(string $start = '09:00'): array
    {
        return [
            'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-11',
            'plantilla_diseno' => [['dia' => 'lunes', 'fecha' => '2026-10-05', 'notas' => 'Nota manual',
                'turnos_requeridos' => [['turno_id' => 'stable-1', '_idLocal' => 'stable-1', 'puesto_num' => 3,
                    'rol_necesario' => 'encargado', 'hora_inicio' => $start, 'hora_fin' => '17:00',
                    'duracion_horas' => 8, 'empleado_id' => $this->employee->id, 'empleado_nombre' => 'Empleado']]]],
            'resultados' => [['turno_id' => 'stable-1', 'empleado_id' => $this->employee->id, 'nombre' => 'Empleado',
                'hora_inicio' => $start, 'hora_fin' => '17:00', 'duracion_horas' => 8]],
            'empleados_snapshot' => [['id' => $this->employee->id, 'nombre' => 'Empleado', 'rol' => 'encargado']],
        ];
    }

    private function open(?string $week = null): array
    {
        return $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => $week ?? $this->week])
            ->assertOk()->json('draft');
    }

    private function payload(array $draft, string $start = '12:00'): array
    {
        return $this->content($start) + ['draft_id' => $draft['id'], 'semana_id' => $draft['semana_id'],
            'version' => $draft['version'], 'request_id' => (string) Str::uuid()];
    }

    private function visible(int $id): void
    {
        $this->actingAs($this->employee)->get('/dashboard/calendario-global?semana='.$this->week)
            ->assertOk()->assertViewHas('id', $id);
        $this->actingAs($this->admin);
    }

    public function test_open_reload_and_autosave_preserve_the_published_snapshot(): void
    {
        $before = $this->publication->getRawOriginal();
        $this->get('/admin/horarios')->assertOk();
        $this->get('/admin/horarios/resultado/'.$this->publication->id)->assertOk();
        $draft = $this->open();
        $this->assertNotSame($this->publication->id, $draft['id']);
        $this->assertSame($draft['id'], $this->open()['id']);
        $this->postJson('/api/horarios-semanales/borrador', $this->payload($draft))->assertOk();
        $this->assertSame($before, $this->publication->fresh()->getRawOriginal());
        $this->visible($this->publication->id);
    }

    public function test_reload_recovers_manual_assignments_times_positions_and_notes(): void
    {
        $draft = $this->open();
        $payload = $this->payload($draft);
        unset($payload['resultados']); // Designer assignments must be flattened, not saved as a layout.
        $this->postJson('/api/horarios-semanales/borrador', $payload)->assertOk();
        $recovered = $this->open();
        $this->assertSame('12:00', $recovered['plantilla_diseno'][0]['turnos_requeridos'][0]['hora_inicio']);
        $this->assertSame(3, $recovered['plantilla_diseno'][0]['turnos_requeridos'][0]['puesto_num']);
        $this->assertSame('Nota manual', $recovered['plantilla_diseno'][0]['notas']);
        $this->assertSame($this->employee->id, $recovered['resultados'][0]['empleado_id']);
        $this->assertArrayNotHasKey('turnos_requeridos', $recovered['resultados'][0]);
    }

    public function test_every_direct_save_route_rejects_a_publication_without_changes(): void
    {
        $before = $this->publication->getRawOriginal();
        $payload = $this->payload($this->publication->toArray());
        $this->postJson('/api/horarios-semanales/borrador', $payload)->assertStatus(409);
        $this->putJson('/admin/horarios/resultado/'.$this->publication->id.'/actualizar', $payload)->assertStatus(409);
        $this->postJson('/admin/horarios/resultado/'.$this->publication->id.'/guardar', $payload)->assertStatus(409);
        $this->assertSame($before, $this->publication->fresh()->getRawOriginal());
    }

    public function test_save_requires_explicit_identity_scope_version_and_admin_permission(): void
    {
        $draft = $this->open();
        $payload = $this->payload($draft);
        $wrongWeek = $payload;
        $wrongWeek['semana_id'] = '2026-W42';
        $this->postJson('/api/horarios-semanales/borrador', $wrongWeek)->assertStatus(422);
        $missingId = $payload;
        unset($missingId['draft_id']);
        $this->postJson('/api/horarios-semanales/borrador', $missingId)->assertStatus(422);
        $this->postJson('/api/horarios-semanales/borrador', $payload + ['centro_id' => 123])->assertStatus(422);
        $this->postJson('/api/horarios-semanales/borrador', $payload + ['roles_publicados' => ['encargado']])->assertStatus(422);
        $this->actingAs($this->employee)->postJson('/api/horarios-semanales/borrador', $payload)->assertForbidden();
        $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => $this->week])->assertForbidden();
        $this->postJson('/admin/horarios/resultado/'.$draft['id'].'/publicar', [
            'semana_id' => $this->week, 'version' => 1, 'roles' => ['encargado'],
        ])->assertForbidden();
        $this->assertSame(1, HorarioSemanal::find($draft['id'])->version);
    }

    public function test_publication_exposes_the_complete_saved_version_and_preserves_old_content(): void
    {
        $before = $this->publication->getRawOriginal();
        $draft = $this->open();
        $payload = $this->payload($draft);
        $saved = $this->postJson('/api/horarios-semanales/borrador', $payload)->assertOk()->json('draft');
        $this->postJson('/admin/horarios/resultado/'.$draft['id'].'/publicar', [
            'semana_id' => $this->week, 'version' => $saved['version'], 'roles' => ['encargado'],
        ])->assertOk()->assertJsonPath('published.published_draft_version', 2);
        $published = HorarioSemanal::find($draft['id']);
        $this->assertSame($payload['plantilla_diseno'], $published->plantilla_diseno);
        $this->assertSame($payload['resultados'], $published->resultados);
        $this->assertSame($before['plantilla_diseno'], $this->publication->fresh()->getRawOriginal('plantilla_diseno'));
        $this->assertSame($before['resultados'], $this->publication->fresh()->getRawOriginal('resultados'));
        $this->assertFalse($this->publication->fresh()->vigente);
        $this->visible($published->id);
        $this->get('/admin/horarios/resultado/'.$published->id)->assertSee('Editar en un borrador independiente');
        $this->post('/admin/horarios/resultado/'.$published->id.'/editar')->assertRedirect();
        $newDraft = $this->open();
        $this->assertNotSame($published->id, $newDraft['id']);
        $this->assertSame($published->resultados, $newDraft['resultados']);
    }

    public function test_delayed_autosave_and_duplicate_publication_cannot_mutate_or_recreate_a_draft(): void
    {
        $draft = $this->open();
        $late = $this->payload($draft);
        $publish = ['semana_id' => $this->week, 'version' => 1, 'roles' => ['encargado']];
        $url = '/admin/horarios/resultado/'.$draft['id'].'/publicar';
        $this->postJson($url, $publish)->assertOk();
        $before = HorarioSemanal::find($draft['id'])->getRawOriginal();
        $this->postJson('/api/horarios-semanales/borrador', $late)->assertStatus(409);
        $this->postJson($url, $publish)->assertStatus(409);
        $this->assertSame(0, HorarioSemanal::where('estado', 'borrador')->count());
        $this->assertSame($before, HorarioSemanal::find($draft['id'])->getRawOriginal());
    }

    public function test_two_tabs_conflict_and_exact_save_retry_is_idempotent(): void
    {
        $first = $this->open();
        $second = $this->open();
        $this->assertSame($first['id'], $second['id']);
        $payload = $this->payload($first, '10:00');
        $this->postJson('/api/horarios-semanales/borrador', $payload)->assertOk()->assertJsonPath('draft.version', 2);
        $this->postJson('/api/horarios-semanales/borrador', $payload)->assertOk()->assertJsonPath('draft.version', 2);
        $this->postJson('/api/horarios-semanales/borrador', $this->payload($second, '14:00'))->assertStatus(409);
        $this->postJson('/admin/horarios/resultado/'.$first['id'].'/publicar', [
            'semana_id' => $this->week, 'version' => 1, 'roles' => ['encargado'],
        ])->assertStatus(409);
        $recovered = $this->open();
        $this->assertSame('10:00', $recovered['resultados'][0]['hora_inicio']);
        $this->assertCount(1, $recovered['resultados']);
        $this->assertSame(2, HorarioSemanal::count());
    }

    public function test_deleting_a_draft_preserves_publication_and_late_requests_do_not_recreate_it(): void
    {
        $draft = $this->open();
        $before = $this->publication->getRawOriginal();
        $this->delete('/admin/horarios/historial/'.$draft['id'], ['semana_id' => $this->week, 'version' => 1])->assertRedirect();
        $this->assertSame($before, $this->publication->fresh()->getRawOriginal());
        $this->postJson('/api/horarios-semanales/borrador', $this->payload($draft))->assertNotFound();
        $this->visible($this->publication->id);
    }

    public function test_other_weeks_and_mutable_templates_remain_independent(): void
    {
        $other = $this->open('2026-W42');
        $draft = $this->open();
        $otherBefore = HorarioSemanal::find($other['id'])->getRawOriginal();
        $templateId = $this->postJson('/api/plantillas', ['nombre' => 'Editable', 'turnos' => $this->content()['plantilla_diseno']])
            ->assertSuccessful()->json('id');
        PlantillaHorario::findOrFail($templateId)->update(['turnos' => []]);
        $this->deleteJson('/api/plantillas/'.$templateId)->assertOk();
        $this->postJson('/api/horarios-semanales/borrador', $this->payload($draft))->assertOk();
        $this->assertSame($otherBefore, HorarioSemanal::find($other['id'])->getRawOriginal());
        $this->assertSame('09:00', $this->publication->fresh()->resultados[0]['hora_inicio']);
    }

    public function test_extra_shifts_share_the_draft_guards_and_never_edit_a_publication(): void
    {
        $before = $this->publication->getRawOriginal();
        $url = '/admin/fichajes/extra/'.$this->employee->id;
        $input = ['fecha' => '2026-10-06', 'hora_inicio' => '13:00', 'hora_fin' => '18:00',
            'draft_id' => $this->publication->id, 'version' => 1, 'request_id' => (string) Str::uuid()];
        $this->postJson($url, $input)->assertStatus(409);
        $draft = $this->open();
        $input['draft_id'] = $draft['id'];
        $this->postJson($url, $input)->assertOk();
        $this->postJson($url, $input)->assertStatus(409);
        $this->assertSame($before, $this->publication->fresh()->getRawOriginal());
        $saved = $this->open();
        $this->assertSame(2, $saved['version']);
        $this->assertCount(2, $saved['resultados']);
        $this->assertSame('martes', $saved['plantilla_diseno'][1]['dia']);
        $this->visible($this->publication->id);
        $input['fecha'] = '2026-10-12';
        $input['version'] = 2;
        $this->postJson($url, $input)->assertUnprocessable();
    }

    public function test_full_manual_layout_changes_survive_reload_and_invalid_assignments_are_rejected(): void
    {
        $draft = $this->open();
        $payload = $this->payload($draft);
        $payload['plantilla_diseno'][0]['dia'] = 'martes';
        $payload['plantilla_diseno'][0]['fecha'] = '2026-10-06';
        $payload['plantilla_diseno'][0]['turnos_requeridos'][0]['turno_id'] = 'manual-new';
        $payload['resultados'][0]['turno_id'] = 'manual-new';
        $saved = $this->postJson('/api/horarios-semanales/borrador', $payload)->assertOk()->json('draft');
        $this->assertSame('martes', $this->open()['plantilla_diseno'][0]['dia']);
        $this->assertSame('manual-new', $this->open()['resultados'][0]['turno_id']);
        $invalid = $this->payload($saved);
        $invalid['resultados'][0]['turno_id'] = 'missing-from-layout';
        $this->postJson('/api/horarios-semanales/borrador', $invalid)->assertUnprocessable();
        $this->assertSame(2, HorarioSemanal::find($draft['id'])->version);
        $empty = $this->payload($saved);
        $empty['plantilla_diseno'] = [];
        $empty['resultados'] = [];
        $this->postJson('/api/horarios-semanales/borrador', $empty)->assertOk();
        $this->assertSame([], $this->open()['resultados']);
        $this->assertSame('stable-1', $this->publication->fresh()->resultados[0]['turno_id']);
    }

    public function test_database_constraint_prevents_duplicate_active_drafts(): void
    {
        $draft = $this->open();
        $this->expectException(UniqueConstraintViolationException::class);
        HorarioSemanal::create($this->content() + ['semana_id' => $this->week, 'estado' => 'borrador', 'draft_slot' => 1]);
    }

    public function test_open_checks_the_source_week_even_when_a_draft_already_exists(): void
    {
        $draft = $this->open();
        $other = $this->open('2026-W42');
        $this->postJson('/api/horarios-semanales/borrador/abrir', [
            'semana_id' => $this->week, 'source_id' => $other['id'],
        ])->assertNotFound();
        $this->assertSame($draft['id'], $this->open()['id']);
        $this->postJson('/api/horarios-semanales/borrador/abrir', ['semana_id' => '2027-W53'])->assertUnprocessable();
    }

    public function test_publication_rolls_back_if_replacing_visibility_fails(): void
    {
        $draft = $this->open();
        $before = HorarioSemanal::find($draft['id'])->getRawOriginal();
        DB::unprepared('CREATE TRIGGER prevent_retirement BEFORE UPDATE OF vigente ON horarios_semanales '
            .'WHEN OLD.id = '.$this->publication->id.' AND NEW.vigente = 0 BEGIN SELECT RAISE(ABORT, \'forced failure\'); END');
        $this->postJson('/admin/horarios/resultado/'.$draft['id'].'/publicar', [
            'semana_id' => $this->week, 'version' => 1, 'roles' => ['encargado'],
        ])->assertStatus(500);
        $this->assertSame($before, HorarioSemanal::find($draft['id'])->getRawOriginal());
        $this->assertTrue($this->publication->fresh()->vigente);
    }

    public function test_generation_finishing_after_publication_is_rejected(): void
    {
        $draft = $this->open();
        Http::fake(function () use ($draft) {
            app(ScheduleDrafts::class)->publish($draft['id'], $this->week, 1, ['encargado']);

            return Http::response(['success' => true, 'resultados' => $this->content('14:00')['resultados']]);
        });
        $this->postJson('/api/generar-horario', [
            'draft_id' => $draft['id'], 'version' => 1, 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => $this->week, 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-11'],
            'plantilla_diseno' => $this->content()['plantilla_diseno'],
            'ajustes_manuales' => [$this->employee->id => 8],
        ])->assertStatus(409);
        $this->assertSame('09:00', HorarioSemanal::find($draft['id'])->resultados[0]['hora_inicio']);
    }

    public function test_infeasible_generation_preserves_the_reason_and_draft(): void
    {
        $draft = $this->open();
        $before = HorarioSemanal::find($draft['id'])->getRawOriginal();
        $message = 'No se encontró solución factible con los datos proporcionados.';
        Http::fake(['127.0.0.1:5000/*' => Http::response(['success' => false, 'message' => $message], 404)]);
        $this->postJson('/api/generar-horario', [
            'draft_id' => $draft['id'], 'version' => $draft['version'], 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => $this->week],
            'plantilla_diseno' => $this->content()['plantilla_diseno'],
            'ajustes_manuales' => [$this->employee->id => 8],
        ])->assertStatus(422)->assertJson(['success' => false, 'message' => $message]);
        $this->assertSame($before, HorarioSemanal::find($draft['id'])->getRawOriginal());
    }

    public function test_partial_generation_saves_and_opens_the_result_without_losing_uncovered_shifts(): void
    {
        $draft = $this->open();
        $layout = $this->content()['plantilla_diseno'];
        $uncovered = $layout[0]['turnos_requeridos'][0];
        $uncovered['turno_id'] = $uncovered['_idLocal'] = 'uncovered';
        $uncovered['empleado_id'] = null;
        $uncovered['empleado_nombre'] = null;
        $layout[0]['turnos_requeridos'][] = $uncovered;
        Http::fake(['127.0.0.1:5000/*' => Http::response([
            'success' => true, 'resultados' => $this->content()['resultados'],
        ])]);
        $response = $this->postJson('/api/generar-horario', [
            'draft_id' => $draft['id'], 'version' => $draft['version'], 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => $this->week, 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-11'],
            'plantilla_diseno' => $layout,
            'ajustes_manuales' => [$this->employee->id => 8],
        ])->assertOk()->assertJsonPath('success', true);
        $saved = HorarioSemanal::findOrFail($draft['id']);
        $this->assertSame(1, $saved->turnos_cubiertos);
        $this->assertSame(2, $saved->turnos_totales);
        $this->assertNull($saved->plantilla_diseno[0]['turnos_requeridos'][1]['empleado_id']);
        $this->assertSame('borrador', $saved->estado);
        $this->assertFalse($saved->vigente);
        $this->get($response->json('redirect_url'))->assertOk()->assertViewIs('admin.horarios-resultado');
        $this->assertSame('09:00', $this->publication->fresh()->resultados[0]['hora_inicio']);
    }

    public function test_missing_python_route_remains_a_server_error(): void
    {
        $draft = $this->open();
        Http::fake(['127.0.0.1:5000/*' => Http::response('<html>Not Found</html>', 404)]);
        $this->postJson('/api/generar-horario', [
            'draft_id' => $draft['id'], 'version' => $draft['version'], 'request_id' => (string) Str::uuid(),
            'metadatos' => ['semana_id' => $this->week],
            'plantilla_diseno' => $this->content()['plantilla_diseno'],
            'ajustes_manuales' => [$this->employee->id => 8],
        ])->assertStatus(404)->assertJson(['message' => 'El servidor de la IA devolvió un error (HTTP 404).']);
    }

    public function test_empty_roles_explicitly_withdraw_visibility_without_deleting_data(): void
    {
        $draft = $this->open();
        $this->postJson('/admin/horarios/resultado/'.$draft['id'].'/publicar', [
            'semana_id' => $this->week, 'version' => 1, 'roles' => [],
        ])->assertOk();
        $this->assertSame(2, HorarioSemanal::count());
        $this->actingAs($this->employee)->get('/dashboard/calendario-global?semana='.$this->week)
            ->assertOk()->assertViewHas('id', null);
    }
}
