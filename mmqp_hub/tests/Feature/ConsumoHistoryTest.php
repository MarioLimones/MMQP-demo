<?php

namespace Tests\Feature;

use App\Models\ConsumoDocument;
use App\Models\ConsumoImport;
use App\Models\ConsumoRecord;
use App\Models\ConsumoSheet;
use App\Models\User;
use App\Services\ConsumoHistory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ConsumoHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['nombre' => 'Ana', 'apellidos' => 'García', 'es_admin' => true, 'activo' => true]);
        $this->actingAs($this->author);
    }

    private function report(): ConsumoImport
    {
        return ConsumoImport::create([
            'file_name' => 'consumos-octubre.pdf', 'original_path' => 'private.pdf', 'row_count' => 1,
            'created_by' => $this->author->id,
            'data' => ['metadata' => ['period_start' => '2026-10-01', 'period_end' => '2026-10-07'],
                'rows' => [['id' => 'source-a', 'name' => 'Cerveza', 'quantity' => '146.000', 'unit' => 'UD']]],
        ]);
    }

    private function payload(?int $importId = null): array
    {
        $supplier = (string) Str::uuid();
        $product = (string) Str::uuid();
        $sources = $importId ? ['source-a'] : [];
        $html = '<tr><td colspan="4" data-consumo-provider="'.$supplier.'">Proveedor</td></tr><tr>';
        foreach (['product' => 'Cerveza', 'consumo' => '', 'stock' => '', 'pedido' => ''] as $field => $value) {
            $html .= '<td data-consumo-product="'.$product.'" data-consumo-supplier="'.$supplier.'" data-consumo-field="'.$field.'"';
            if ($field === 'consumo') {
                $html .= ' data-consumo-sources="'.htmlspecialchars(json_encode($sources)).'"';
            }
            $html .= '><div class="cell-content">'.$value.'</div></td>';
        }
        $html .= '</tr>';

        return [
            'title' => 'Consumo semanal', 'import_id' => $importId,
            'suppliers' => [['id' => $supplier, 'name' => 'Proveedor', 'products' => [[
                'id' => $product, 'name' => 'Cerveza', 'stock' => '', 'pedido' => '', 'source_ids' => $sources,
            ]]]],
            'pages' => [
                ['id' => (string) Str::uuid(), 'title' => 'Consumos', 'grid_html' => $html],
                ['id' => (string) Str::uuid(), 'title' => 'Pedidos', 'grid_html' => '<tr><td><div class="cell-content">Pedido original</div></td></tr>'],
            ],
        ];
    }

    private function editable(array $sheet): array
    {
        unset($sheet['updated_at']);
        foreach ($sheet['suppliers'] as &$supplier) {
            foreach ($supplier['products'] as &$product) {
                unset($product['consumption']);
            }
        }

        return $sheet;
    }

    private function at(string $time, ?User $actor = null): ConsumoRecord
    {
        $sheet = ConsumoSheet::create([
            'title' => 'Ficha '.$time, 'payload' => ['suppliers' => [], 'grid_html' => '<tr><td>Guardado</td></tr>'],
            'version' => 1, 'created_by' => $this->author->id,
        ]);

        return app(ConsumoHistory::class)->record($sheet, $actor ?? $this->author, CarbonImmutable::parse($time, 'UTC'));
    }

    public function test_every_save_preserves_an_exact_independent_snapshot_with_all_pages_and_its_actual_author(): void
    {
        $report = $this->report();
        $first = $this->postJson('/admin/consumos/fichas', $this->payload($report->id))->assertOk()->json('sheet');
        $record = ConsumoRecord::firstOrFail();
        $this->assertSame($first, $record->snapshot);
        $this->assertSame('146.000', $record->snapshot['suppliers'][0]['products'][0]['consumption']['amount']);
        $this->assertCount(2, $record->snapshot['pages']);
        $this->assertSame('Ana García', $record->actor_name);

        $other = User::factory()->create(['nombre' => 'Luis', 'apellidos' => 'Pérez', 'es_admin' => true]);
        $update = $this->editable($first);
        $update['title'] = 'Ficha revisada';
        $update['suppliers'][0]['products'][0]['stock'] = '18 cajas';
        $second = $this->actingAs($other)->postJson('/admin/consumos/fichas', $update)->assertOk()->json('sheet');
        $this->assertDatabaseCount('consumo_records', 2);
        $this->assertSame($first, $record->fresh()->snapshot);
        $this->assertSame($second, ConsumoRecord::latest('id')->firstOrFail()->snapshot);
        $this->assertSame('Luis Pérez', ConsumoRecord::latest('id')->firstOrFail()->actor_name);

        $this->author->update(['nombre' => 'Nombre cambiado']);
        $this->author->delete();
        $this->assertNull($record->fresh()->actor_id);
        $this->assertSame('Ana García', $record->fresh()->actor_name);
        $report->update(['file_name' => 'cambiado.pdf', 'data' => ['metadata' => [], 'rows' => []]]);
        ConsumoDocument::create(['scope' => 'default', 'title' => 'Plantilla distinta', 'version' => 1, 'payload' => ['suppliers' => []]]);
        $view = $this->get('/admin/consumos/historial/'.$record->id)->assertOk()->assertViewIs('admin.consumos.history-show');
        $this->assertSame('146.000', $view->viewData('snapshot')['suppliers'][0]['products'][0]['consumption']['amount']);
        $this->assertSame('consumos-octubre.pdf', $view->viewData('snapshot')['import_metadata']['file_name']);
        $this->assertSame($first, $record->fresh()->snapshot);
    }

    public function test_template_and_pending_pdf_are_excluded_and_completing_twice_does_not_duplicate_history(): void
    {
        $payload = $this->payload();
        unset($payload['import_id']);
        $document = $this->postJson('/admin/consumos/documento', $payload)->assertOk()->json('document');
        $import = $this->report();
        $this->assertDatabaseCount('consumo_records', 0);
        $body = ['document_id' => $document['id'], 'document_version' => $document['version'], 'review_all' => true,
            'links' => [['product_id' => $payload['suppliers'][0]['products'][0]['id'], 'source_ids' => ['source-a']]]];
        $saved = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', $body)->assertCreated()->json('sheet');
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', $body)->assertOk()->assertJsonPath('sheet.id', $saved['id']);
        $this->assertDatabaseCount('consumo_records', 1);
        $this->assertSame($saved, ConsumoRecord::firstOrFail()->snapshot);
    }

    public function test_saves_without_an_identifiable_account_fail_without_leaving_a_sheet_or_history(): void
    {
        $this->author->update(['nombre' => ' ', 'apellidos' => ' ']);
        $this->postJson('/admin/consumos/fichas', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('author');
        $this->assertDatabaseCount('consumo_sheets', 0);
        $this->assertDatabaseCount('consumo_records', 0);
    }

    public function test_missing_authors_are_rejected_and_client_supplied_names_cannot_replace_the_signed_in_user(): void
    {
        $payload = [...$this->payload(), 'actor_name' => 'Autor inventado', 'actor_id' => 999];
        $sheet = $this->postJson('/admin/consumos/fichas', $payload)->assertOk()->json('sheet');
        $record = ConsumoRecord::firstOrFail();
        $this->assertSame($this->author->id, $record->actor_id);
        $this->assertSame('Ana García', $record->actor_name);
        $this->assertSame($sheet['id'], $record->sheet_id);
        try {
            app(ConsumoHistory::class)->record(ConsumoSheet::findOrFail($sheet['id']), null);
            $this->fail('An unidentified author must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('author', $exception->errors());
        }
        $this->assertDatabaseCount('consumo_records', 1);
    }

    public function test_calendar_uses_madrid_dates_preserves_multiple_saves_and_excludes_neighbouring_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00', 'UTC'));
        $included = $this->at('2026-09-30 22:30:00');
        $this->at('2026-09-30 21:59:00');
        $this->at('2026-10-01 09:00:00');
        $this->at('2026-10-31 23:00:00');
        $calendar = app(ConsumoHistory::class)->calendar(['month' => '2026-10', 'day' => '2026-10-01']);
        $this->assertSame('octubre de 2026', $calendar['month_label']);
        $this->assertSame('2026-09', $calendar['previous_month']);
        $this->assertSame('2026-11', $calendar['next_month']);
        $this->assertSame(2, $calendar['total_records']);
        $this->assertCount(2, $calendar['selected_records']);
        $entry = collect($calendar['selected_records'])->firstWhere('id', $included->id);
        $this->assertSame('00:30', $entry['registered_time']);
        $this->assertSame('2026-10-01', $entry['date']);
        $this->assertSame('2026-09-30', $calendar['days'][2]['date']);
        $this->assertFalse($calendar['days'][2]['in_month']);
        $this->assertSame([], $calendar['days'][2]['records']);
        $this->assertSame('2026-10-21', $calendar['today']);
        $this->assertCount(42, $calendar['days']);
        $this->assertCount(42, app(ConsumoHistory::class)->calendar(['month' => '2026-08'])['days']);
        $this->assertCount(42, app(ConsumoHistory::class)->calendar(['month' => '2021-02'])['days']);
    }

    public function test_calendar_validates_month_and_selected_day_on_both_routes(): void
    {
        foreach (['/admin/consumos', '/admin/consumos/historial'] as $url) {
            foreach (['month=2026-13', 'month=2026-2', 'day=2026-02-31', 'month=2026-10&day=2026-09-30'] as $query) {
                $this->getJson($url.'?'.$query)->assertUnprocessable();
            }
        }
    }

    public function test_future_days_use_the_current_madrid_date_including_across_months(): void
    {
        // It is already October 22 in Madrid while the UTC date is still October 21.
        $this->travelTo(CarbonImmutable::parse('2026-10-21 22:30:00', 'UTC'));
        $calendar = app(ConsumoHistory::class)->calendar(['month' => '2026-10']);
        $days = collect($calendar['days'])->keyBy('date');
        $this->assertFalse($days['2026-10-21']['is_future']);
        $this->assertFalse($days['2026-10-22']['is_future']);
        $this->assertTrue($days['2026-10-22']['is_today']);
        $this->assertTrue($days['2026-10-23']['is_future']);
        $this->assertTrue($days['2026-11-01']['is_future']);
        $this->assertFalse(collect(app(ConsumoHistory::class)->calendar(['month' => '2026-09'])['days'])->contains('is_future', true));
        $this->assertFalse(collect(app(ConsumoHistory::class)->calendar(['month' => '2026-11'])['days'])->contains('is_future', false));
        $this->get('/admin/consumos/historial?month=2026-10&day=2026-10-22')->assertOk()
            ->assertSee('is-future', false)->assertSee('Día futuro')->assertSee('data-history-month="2026-10"', false);
    }

    public function test_history_pages_require_authentication_and_admin_access(): void
    {
        $record = $this->at('2026-10-21 12:00:00');
        $urls = ['/admin/consumos/historial', '/admin/consumos/historial/'.$record->id];
        $this->actingAs(User::factory()->create(['es_admin' => false]));
        foreach ($urls as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_backfill_uses_only_known_authors_and_the_saved_state_timestamp(): void
    {
        $record = $this->at('2026-10-01 08:00:00');
        DB::table('consumo_records')->delete();
        $sheet = ConsumoSheet::findOrFail($record->sheet_id);
        DB::table('consumo_sheets')->where('id', $sheet->id)->update(['created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 20:30:00']);
        $sheet->refresh();
        $first = app(ConsumoHistory::class)->backfill($sheet);
        $this->assertSame('Ana García', $first->actor_name);
        $this->assertSame('2026-10-01', $first->registration_date->toDateString());
        DB::table('consumo_sheets')->where('id', $sheet->id)->update(['version' => 2, 'updated_at' => '2026-10-02 22:30:00']);
        $sheet->refresh();
        $second = app(ConsumoHistory::class)->backfill($sheet);
        $this->assertSame('Autor no registrado', $second->actor_name);
        $this->assertNull($second->actor_id);
        $this->assertSame('2026-10-03', $second->registration_date->toDateString());
        $this->assertSame('2026-10-02T22:30:00.000000Z', $second->registered_at->toISOString());
        app(ConsumoHistory::class)->backfill($sheet);
        $this->assertDatabaseCount('consumo_records', 2);
    }

    public function test_conflicts_and_failed_archive_never_leave_partial_sheet_or_history_changes(): void
    {
        $first = $this->postJson('/admin/consumos/fichas', $this->payload())->assertOk()->json('sheet');
        $update = $this->editable($first);
        $this->postJson('/admin/consumos/fichas', $update)->assertOk();
        $this->postJson('/admin/consumos/fichas', $update)->assertStatus(409);
        $this->assertDatabaseCount('consumo_records', 2);
        $this->mock(ConsumoHistory::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Archive failure'));
        });
        $this->postJson('/admin/consumos/fichas', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('consumo_sheets', 1);
        $this->assertDatabaseCount('consumo_records', 2);
    }

    public function test_archive_failure_rolls_back_completed_import_links_and_document_changes(): void
    {
        $payload = $this->payload();
        unset($payload['import_id']);
        $document = $this->postJson('/admin/consumos/documento', $payload)->assertOk()->json('document');
        $before = ConsumoDocument::firstOrFail()->toArray();
        $import = $this->report();
        $this->mock(ConsumoHistory::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Archive failure'));
        });
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            'document_id' => $document['id'], 'document_version' => $document['version'], 'review_all' => true,
            'links' => [['product_id' => $payload['suppliers'][0]['products'][0]['id'], 'source_ids' => ['source-a']]],
        ])->assertStatus(500);
        $this->assertDatabaseCount('consumo_sheets', 0);
        $this->assertDatabaseCount('consumo_records', 0);
        $this->assertDatabaseCount('consumo_product_links', 0);
        $this->assertSame($before, ConsumoDocument::firstOrFail()->toArray());
    }

    public function test_display_strips_editable_attributes_events_scripts_and_controls_without_mutating_the_archive(): void
    {
        $record = $this->at('2026-10-21 12:00:00');
        $snapshot = $record->snapshot;
        $snapshot['pages'][0]['grid_html'] = '<tr><td tabindex="0" contenteditable="true" onclick="alert(1)"><div contenteditable="true">Conservado</div><script>alert(2)</script><button>Modificar</button></td></tr>';
        DB::table('consumo_records')->where('id', $record->id)->update(['snapshot' => json_encode($snapshot)]);
        $record->refresh();
        $safe = app(ConsumoHistory::class)->viewSnapshot($record);
        foreach (['contenteditable', 'tabindex', 'onclick', '<script', '<button'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $safe['pages'][0]['grid_html']);
        }
        $this->assertStringContainsString('Conservado', $safe['pages'][0]['grid_html']);
        $this->assertSame($snapshot, $record->fresh()->snapshot);
    }

    public function test_saved_records_cannot_be_overwritten_through_the_model(): void
    {
        $record = $this->at('2026-10-21 12:00:00');
        $this->expectException(LogicException::class);
        $record->update(['title' => 'Sobrescrito']);
    }
}
