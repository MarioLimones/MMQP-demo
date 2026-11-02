<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ArqueoTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->temporaryStorage = sys_get_temp_dir().'/mmqp-arqueo-test-'.Str::uuid();
        mkdir($this->temporaryStorage.'/app', 0777, true);
        $this->app->useStoragePath($this->temporaryStorage);
        $this->actingAs(User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']));
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        // Every test writes only to its own temporary directory, never the real design.
        (new Filesystem)->deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function path(): string
    {
        return $this->temporaryStorage.'/app/arqueo_template.json';
    }

    private function save(string $html)
    {
        return $this->postJson('/admin/arqueo/template', ['template' => ['html' => $html]]);
    }

    public function test_admin_entry_offers_document_and_daily_workflows_without_opening_the_editor(): void
    {
        $this->save('<tr><td>PLANTILLA GUARDADA</td></tr>')->assertOk();
        $before = file_get_contents($this->path());

        $this->get('/admin/arqueo?fecha=2026-10-12')->assertOk()
            ->assertViewHas('workflowMode', 'entry')
            ->assertSee('¿Qué necesitas hacer?')
            ->assertSee('Editar documento')
            ->assertSee('Rellenar arqueo')
            ->assertSee('modo=editar', false)
            ->assertSee('modo=rellenar', false)
            ->assertDontSee('id="arqueo-grid"', false)
            ->assertDontSee('id="btn-edit-mode"', false)
            ->assertDontSee('id="btn-export-template"', false)
            ->assertDontSee('id="form-fecha"', false);
        $this->assertSame($before, file_get_contents($this->path()));
    }

    public function test_saved_format_and_bindings_are_loaded_without_cached_automatic_values(): void
    {
        $html = '<tr><td colspan="4" rowspan="2" class="b-top b-left solid-border" data-bound-emp="27" data-bound-prop="manana" style="background-color: rgb(225, 225, 225); text-align: center"><div class="cell-content" contenteditable="true" style="font-size: 12px; font-weight: bold">José — MAÑANA<br>12:00–16:00</div></td><td data-bound-date="true"><div class="cell-content">12/10/2026</div></td></tr>';

        $this->save($html)->assertOk()->assertJson(['success' => true]);
        $saved = json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('colspan="4" rowspan="2"', $saved['html']);
        $this->assertStringContainsString('class="b-top b-left solid-border"', $saved['html']);
        $this->assertStringContainsString('data-bound-emp="27" data-bound-prop="manana"', $saved['html']);
        $this->assertStringContainsString('font-size: 12px; font-weight: bold', $saved['html']);
        $this->assertStringNotContainsString('José — MAÑANA', $saved['html']);
        $this->assertStringNotContainsString('12:00–16:00', $saved['html']);
        $before = file_get_contents($this->path());

        $this->get('/admin/arqueo?modo=editar&fecha=2026-10-12')->assertOk()
            ->assertViewHas('templateData', fn ($data) => $data['html'] === $saved['html'])->assertViewHas('templateWarning', fn ($warning) => $warning === null);
        $this->assertSame($before, file_get_contents($this->path()));
    }

    public function test_invalid_payloads_cannot_replace_a_saved_design(): void
    {
        $this->save('<tr><td><div class="cell-content">INGRESOS</div></td></tr>')->assertOk();
        $before = file_get_contents($this->path());

        foreach ([[], ['template' => 'incorrecto'], ['template' => ['html' => []]], ['template' => ['html' => '']], ['template' => ['html' => '<script>alert(1)</script>']]] as $payload) {
            $this->postJson('/admin/arqueo/template', $payload)->assertUnprocessable();
            $this->assertSame($before, file_get_contents($this->path()));
        }
    }

    public function test_legacy_sheets_larger_than_a4_keep_all_rows_and_merged_cells(): void
    {
        $html = '<tr><td rowspan="80" colspan="30"><div class="cell-content">HOJA ANTIGUA</div></td><td><div class="cell-content">PRIMERA FILA</div></td></tr>';
        for ($row = 1; $row < 80; $row++) {
            $html .= '<tr><td><div class="cell-content">'.($row === 79 ? 'ÚLTIMA FILA CONSERVADA' : 'FILA '.$row).'</div></td></tr>';
        }

        $this->save($html)->assertOk();
        $saved = json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('rowspan="80" colspan="30"', $saved['html']);
        $this->assertSame(80, substr_count($saved['html'], '<tr>'));
        $this->assertStringContainsString('ÚLTIMA FILA CONSERVADA', $saved['html']);

        $this->get('/admin/arqueo?modo=editar&fecha=2026-10-12')->assertOk()->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'ÚLTIMA FILA CONSERVADA'));
        $this->assertSame($saved, json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_pasted_active_html_is_removed_while_table_text_is_retained(): void
    {
        $this->save('<tr onclick="alert(1)"><td style="background: url(https://invalid.example/image); font-weight: bold; position: fixed"><div class="cell-content" onfocus="alert(1)">GASTOS<script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">Ticket</a><iframe src="https://invalid.example"></iframe></div></td></tr>')
            ->assertOk();

        $saved = json_decode(file_get_contents($this->path()), true)['html'];
        $this->assertStringContainsString('GASTOSTicket', $saved);
        $this->assertStringContainsString('font-weight: bold', $saved);
        foreach (['onclick', 'onfocus', 'onerror', '<script', '<img', '<iframe', 'javascript:', 'url(', 'position:'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $saved);
        }
    }

    public function test_existing_templates_are_safely_loaded_without_rewriting_the_original(): void
    {
        $legacy = json_encode(['html' => '<tr><td><div class="cell-content" onclick="alert(1)">CAJA<script>alert(1)</script></div></td></tr>']);
        file_put_contents($this->path(), $legacy);

        $response = $this->get('/admin/arqueo?modo=editar&fecha=2026-10-12')->assertOk();
        $response->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'CAJA') && ! str_contains($data['html'], '<script'));
        $this->get('/admin/arqueo?modo=editar&fecha=2026-10-13')->assertOk()->assertViewHas('templateData', fn ($data) => str_contains($data['html'], 'CAJA'));
        $this->assertSame($legacy, file_get_contents($this->path()));
    }

    public function test_invalid_dates_return_validation_errors_instead_of_a_server_error(): void
    {
        foreach (['no-es-fecha', '2026-02-30', '12/10/2026', 'tomorrow'] as $date) {
            $this->getJson('/admin/arqueo?fecha='.urlencode($date))
                ->assertUnprocessable()->assertJsonValidationErrors('fecha');
        }

        $this->get('/admin/arqueo?fecha=2026-10-12')->assertOk()->assertViewHas('dateStr', '2026-10-12');
    }

    public function test_entry_ignores_even_corrupt_saved_templates_without_rewriting_them(): void
    {
        foreach (['{"html":', '{"other":"legacy"}', 'null'] as $corrupt) {
            file_put_contents($this->path(), $corrupt);
            $this->get('/admin/arqueo?fecha=2026-10-12')->assertOk()
                ->assertViewHas('workflowMode', 'entry')
                ->assertViewHas('templateData', null)
                ->assertViewHas('templateWarning', null);
            $this->assertSame($corrupt, file_get_contents($this->path()));
        }
    }

    public function test_storage_failure_does_not_report_success_or_overwrite_the_previous_template(): void
    {
        $previous = '{"html":"<tr><td>ORIGINAL</td></tr>"}';
        file_put_contents($this->path(), $previous);
        File::partialMock();
        File::shouldReceive('ensureDirectoryExists')->once()->with(storage_path('app'));
        File::shouldReceive('replace')->once()->andThrow(new RuntimeException('Storage unavailable'));

        $this->save('<tr><td>NUEVO</td></tr>')->assertStatus(500)->assertJson([
            'success' => false,
            'message' => 'No se ha podido guardar el diseño. Conserva esta página abierta y vuelve a intentarlo.',
        ]);
        $this->assertSame($previous, file_get_contents($this->path()));
    }

    public function test_an_employee_cannot_overwrite_the_admin_template(): void
    {
        $this->actingAs(User::factory()->create(['es_admin' => false, 'rol' => 'auxiliar']));
        $this->save('<tr><td>CAMBIO</td></tr>')->assertForbidden();
        $this->assertFileDoesNotExist($this->path());
    }

    public function test_imported_geometry_survives_save_and_reload_with_format_and_merged_cells(): void
    {
        $html = '<tr data-arqueo-columns="[25,25,50]" data-arqueo-rows="[76,200]"><td rowspan="2" class="b-left"><div class="cell-content">CAJA</div></td><td colspan="2" class="b-bottom"><div class="cell-content" style="font-weight: bold">TOTAL</div></td></tr><tr><td><div class="cell-content">A</div></td><td><div class="cell-content">B</div></td></tr>';
        $this->save($html)->assertOk();
        $saved = json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($html, $saved['html']);
        $this->get('/admin/arqueo?modo=editar&fecha=2026-10-12')->assertOk()->assertViewHas('templateData', fn ($data) => $data['html'] === $saved['html']);
    }

    public function test_invalid_or_misplaced_import_geometry_cannot_overwrite_a_design(): void
    {
        $this->save('<tr><td>ORIGINAL</td></tr>')->assertOk();
        $before = file_get_contents($this->path());
        foreach ([
            '<tr data-arqueo-columns="[100]"><td>A</td></tr>',
            '<tr data-arqueo-columns="[-20,120]" data-arqueo-rows="[276]"><td>A</td><td>B</td></tr>',
            '<tr data-arqueo-columns="[100]" data-arqueo-rows="[200]"><td>A</td></tr>',
            '<tr data-arqueo-columns="[100]" data-arqueo-rows="[138,138]"><td>A</td></tr>',
            '<tr data-arqueo-columns="[50,50]" data-arqueo-rows="[276]"><td>A</td></tr>',
            '<tr data-arqueo-columns="[100]" data-arqueo-rows="[276]"><td rowspan="2">A</td></tr>',
            '<tr><td data-arqueo-columns="[100]" data-arqueo-rows="[276]">A</td></tr>',
            '<tr><td>A</td></tr><tr data-arqueo-columns="[100]" data-arqueo-rows="[138,138]"><td>B</td></tr>',
            '<tr data-arqueo-columns="{&quot;0&quot;:100}" data-arqueo-rows="[276]"><td>A</td></tr>',
        ] as $html) {
            $this->save($html)->assertUnprocessable()->assertJsonValidationErrors('template.html');
            $this->assertSame($before, file_get_contents($this->path()));
        }
    }

    public function test_the_editor_provides_local_template_transfer_without_a_pdf_upload(): void
    {
        $this->get('/admin/arqueo?modo=editar&fecha=2026-10-12')->assertOk()
            ->assertViewHas('workflowMode', 'design')
            ->assertSee('id="btn-export-template"', false)
            ->assertSee('id="btn-import-template"', false)
            ->assertDontSee('id="btn-edit-mode"', false)
            ->assertDontSee('id="form-fecha"', false)
            ->assertSee('application/json', false)
            ->assertDontSee('application/pdf', false)
            ->assertDontSee('image/png', false);
        $this->assertFileDoesNotExist($this->path());
    }
}
