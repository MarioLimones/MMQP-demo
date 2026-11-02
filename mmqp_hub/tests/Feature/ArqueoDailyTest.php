<?php

namespace Tests\Feature;

use App\Models\HorarioSemanal;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ArqueoDailyTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStorage;
    private string $temporaryStorage;
    private User $admin;
    private User $employee;
    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->temporaryStorage = sys_get_temp_dir().'/mmqp-arqueo-daily-'.Str::uuid();
        mkdir($this->temporaryStorage.'/app', 0777, true);
        $this->app->useStoragePath($this->temporaryStorage);
        $this->admin = User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']);
        $this->employee = User::factory()->create(['es_admin' => false, 'activo' => true, 'rol' => 'auxiliar', 'nombre' => 'Lucía']);
        $html = '<tr><td><div class="cell-content">INGRESOS</div></td><td class="b-bottom" data-arqueo-input="true"><div class="cell-content">507,71</div></td><td data-bound-emp="'.$this->employee->id.'" data-bound-prop="nombre"><div class="cell-content">NOMBRE ANTIGUO</div></td><td data-bound-emp="'.$this->employee->id.'" data-bound-prop="manana"><div class="cell-content">08:00-10:00</div></td><td data-bound-date="true"><div class="cell-content">FECHA ANTIGUA</div></td></tr>';
        $this->hash = $this->actingAs($this->admin)->postJson('/admin/arqueo/template', ['template' => ['html' => $html]])->assertOk()->json('templateHash');
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        (new Filesystem)->deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function saveDay(array $extra = [])
    {
        return $this->postJson('/arqueo/dia', $extra + ['fecha' => '2026-10-19', 'templateHash' => $this->hash, 'values' => ['0-1' => "123,45\nSegundo ingreso"]]);
    }

    public function test_administrator_can_fill_save_reopen_and_start_a_clean_next_day_without_changing_template(): void
    {
        $before = file_get_contents($this->temporaryStorage.'/app/arqueo_template.json');
        $this->assertStringNotContainsString('507,71', $before);
        $this->actingAs($this->admin)->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()
            ->assertSee('onclick="printArqueo()"', false)->assertDontSee('Guardar arqueo')->assertDontSee('id="btn-edit-mode"', false)
            ->assertViewHas('canDesign', true)->assertViewHas('dailySaved', false);
        $this->saveDay()->assertOk();
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()->assertViewHas('dailySaved', true)
            ->assertViewHas('templateData', fn ($data) => str_contains($data['html'], "123,45\nSegundo ingreso") && str_contains($data['html'], 'INGRESOS'));
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-20')->assertOk()->assertViewHas('dailySaved', false)
            ->assertViewHas('templateData', fn ($data) => ! str_contains($data['html'], '123,45') && str_contains($data['html'], 'data-bound-emp'));
        $this->assertSame($before, file_get_contents($this->temporaryStorage.'/app/arqueo_template.json'));
        $this->actingAs($this->employee)->postJson('/admin/arqueo/template', ['template' => ['html' => '<tr><td>CAMBIO</td></tr>']])->assertForbidden();
    }

    public function test_values_cannot_replace_labels_or_bindings_or_execute_html(): void
    {
        $this->actingAs($this->admin);
        $this->saveDay(['values' => ['0-0' => 'TITULO CAMBIADO', '0-1' => '<script>alert(1)</script>', '0-2' => 'OTRO EMPLEADO']])->assertOk();
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'INGRESOS') && ! str_contains($data['html'], 'TITULO CAMBIADO') && ! str_contains($data['html'], 'OTRO EMPLEADO') && ! str_contains($data['html'], '<script>') && str_contains($data['html'], '&lt;script&gt;'));
    }

    public function test_edit_document_uses_the_base_template_while_fill_reopens_the_historical_daily_copy(): void
    {
        $this->saveDay()->assertOk();
        $dailyPath = $this->temporaryStorage.'/app/arqueos/2026-10-19.json';
        $dailyBefore = file_get_contents($dailyPath);
        $newHash = $this->postJson('/admin/arqueo/template', [
            'template' => ['html' => '<tr><td>PLANTILLA ACTUAL</td><td data-arqueo-input="true"><div class="cell-content"></div></td></tr>'],
        ])->assertOk()->json('templateHash');
        $templatePath = $this->temporaryStorage.'/app/arqueo_template.json';
        $templateBefore = file_get_contents($templatePath);

        $this->get('/admin/arqueo?modo=editar&fecha=2026-10-19')->assertOk()
            ->assertViewHas('workflowMode', 'design')
            ->assertViewHas('dailySaved', false)
            ->assertViewHas('templateHash', $newHash)
            ->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'PLANTILLA ACTUAL') && ! str_contains($data['html'], 'INGRESOS') && ! str_contains($data['html'], '123,45'));

        $this->get('/admin/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()
            ->assertViewHas('workflowMode', 'daily')
            ->assertViewHas('dailySaved', true)
            ->assertViewHas('templateHash', $this->hash)
            ->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'INGRESOS') && str_contains($data['html'], "123,45\nSegundo ingreso") && ! str_contains($data['html'], 'PLANTILLA ACTUAL'))
            ->assertSee('id="form-fecha"', false)
            ->assertDontSee('id="btn-export-template"', false)
            ->assertDontSee('id="btn-import-template"', false)
            ->assertDontSee('id="btn-edit-mode"', false);

        $this->assertSame($dailyBefore, file_get_contents($dailyPath));
        $this->assertSame($templateBefore, file_get_contents($templatePath));
    }

    public function test_employee_cannot_open_or_save_arqueo_even_when_a_mode_is_requested(): void
    {
        $before = file_get_contents($this->temporaryStorage.'/app/arqueo_template.json');
        $this->actingAs($this->employee)->get('/arqueo?modo=editar&fecha=2026-10-19')->assertForbidden();
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertForbidden();
        $this->saveDay()->assertForbidden();
        $this->assertFalse(is_dir($this->temporaryStorage.'/app/arqueos'));
        $this->assertSame($before, file_get_contents($this->temporaryStorage.'/app/arqueo_template.json'));
    }

    public function test_invalid_date_values_and_stale_template_do_not_overwrite_a_saved_day(): void
    {
        $this->saveDay()->assertOk();
        $path = $this->temporaryStorage.'/app/arqueos/2026-10-19.json';
        $before = file_get_contents($path);
        $this->saveDay(['templateHash' => str_repeat('a', 64)])->assertStatus(409);
        foreach ([['fecha' => '../bad'], ['fecha' => '2026-02-30'], ['values' => 'invalid'], ['values' => ['0-1' => str_repeat('a', 10001)]]] as $invalid) {
            $this->saveDay($invalid)->assertUnprocessable();
        }
        $this->assertSame($before, file_get_contents($path));
    }

    public function test_saved_days_keep_their_layout_after_the_base_template_changes(): void
    {
        $this->saveDay()->assertOk();
        $this->postJson('/admin/arqueo/template', ['template' => ['html' => '<tr><td>PLANTILLA NUEVA</td><td><div class="cell-content"></div></td></tr>']])->assertOk();
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()->assertViewHas('templateData', fn ($data) => str_contains($data['html'], '123,45') && ! str_contains($data['html'], 'PLANTILLA NUEVA'));
        $this->saveDay(['values' => ['0-1' => '456,78']])->assertOk();
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-20')->assertOk()->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'PLANTILLA NUEVA') && ! str_contains($data['html'], '456,78'));
    }

    public function test_automatic_shifts_follow_the_selected_day_and_ignore_newer_drafts(): void
    {
        $days = [['dia' => 'lunes', 'turnos_requeridos' => [['turno_id' => 'mon', 'hora_inicio' => '12:00', 'hora_fin' => '16:30']]], ['dia' => 'martes', 'turnos_requeridos' => [['turno_id' => 'tue', 'hora_inicio' => '20:00', 'hora_fin' => '00:30']]]];
        $data = ['semana_id' => '2026-W43', 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-25', 'estado' => 'published', 'vigente' => true, 'generado_por' => $this->admin->id, 'plantilla_diseno' => $days, 'empleados_snapshot' => [['id' => $this->employee->id, 'nombre' => 'Lucía']], 'resultados' => [['turno_id' => 'mon', 'empleado_id' => $this->employee->id], ['turno_id' => 'tue', 'empleado_id' => $this->employee->id]]];
        HorarioSemanal::create($data);
        HorarioSemanal::create(array_replace($data, ['estado' => 'borrador', 'vigente' => false, 'resultados' => []]));
        $id = $this->employee->id;
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()->assertViewHas('empleadosDia', fn ($employees) => $employees[$id]['manana'] === '12:00-16:30' && $employees[$id]['noche'] === null);
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-20')->assertOk()->assertViewHas('empleadosDia', fn ($employees) => $employees[$id]['manana'] === null && $employees[$id]['noche'] === '20:00-00:30');
    }

    public function test_corrupt_saved_files_are_reported_and_cannot_be_overwritten(): void
    {
        mkdir($this->temporaryStorage.'/app/arqueos');
        $path = $this->temporaryStorage.'/app/arqueos/2026-10-19.json';
        file_put_contents($path, '{corrupt');
        $this->get('/arqueo?modo=rellenar&fecha=2026-10-19')->assertOk()->assertViewHas('dailyAvailable', false)->assertViewHas('templateWarning', fn ($message) => is_string($message));
        $this->saveDay()->assertStatus(500);
        $this->assertSame('{corrupt', file_get_contents($path));
    }

    public function test_failed_save_does_not_report_success_or_lose_the_previous_day(): void
    {
        $this->saveDay()->assertOk();
        $path = $this->temporaryStorage.'/app/arqueos/2026-10-19.json';
        $before = file_get_contents($path);
        File::partialMock();
        File::shouldReceive('replace')->once()->andThrow(new RuntimeException('Storage unavailable'));
        $this->saveDay(['values' => ['0-1' => 'NUEVO']])->assertStatus(500)->assertJson(['success' => false]);
        $this->assertSame($before, file_get_contents($path));
    }

    public function test_daily_access_requires_authentication(): void
    {
        auth()->logout();
        $this->get('/arqueo')->assertRedirect('/login');
        $this->postJson('/arqueo/dia', [])->assertUnauthorized();
    }
}
