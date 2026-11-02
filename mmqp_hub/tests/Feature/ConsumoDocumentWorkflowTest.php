<?php

namespace Tests\Feature;

use App\Models\ConsumoDocument;
use App\Models\ConsumoImport;
use App\Models\ConsumoProductLink;
use App\Models\ConsumoSheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsumoDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['es_admin' => true, 'activo' => true, 'rol' => 'oficina']));
    }

    private function product(string $name): array
    {
        return ['id' => (string) Str::uuid(), 'name' => $name, 'stock' => '', 'pedido' => '', 'source_ids' => []];
    }

    private function payload(array $products): array
    {
        $payload = ['title' => 'Documento semanal', 'suppliers' => [['id' => (string) Str::uuid(), 'name' => 'Proveedor', 'products' => $products]]];
        $payload['grid_html'] = $this->grid($payload['suppliers']);

        return $payload;
    }

    private function grid(array $suppliers): string
    {
        $html = '';
        foreach ($suppliers as $supplier) {
            $html .= '<tr><td colspan="4" data-consumo-provider="'.$supplier['id'].'">'.$supplier['name'].'</td></tr>';
            foreach ($supplier['products'] as $product) {
                $html .= '<tr>';
                foreach (['product', 'consumo', 'stock', 'pedido'] as $field) {
                    $html .= '<td data-consumo-product="'.$product['id'].'" data-consumo-supplier="'.$supplier['id'].'" data-consumo-field="'.$field.'"';
                    if ($field === 'consumo') {
                        $html .= ' data-consumo-sources="'.htmlspecialchars(json_encode($product['source_ids'])).'"';
                    }
                    $html .= '><div class="cell-content">'.htmlspecialchars($field === 'product' ? $product['name'] : ($field === 'consumo' ? '999 inventado' : $product[$field])).'</div></td>';
                }
                $html .= '</tr>';
            }
        }

        return $html;
    }

    private function saveDocument(array $payload): array
    {
        return $this->postJson('/admin/consumos/documento', $payload)->assertOk()->json('document');
    }

    private function row(string $identity, string $name = 'Barbacoa', string $quantity = '1.000', string $unit = 'KG', string $category = 'Salsas'): array
    {
        return ['id' => (string) Str::uuid().':'.$identity, 'original_source_id' => $identity, 'name' => $name,
            'quantity' => $quantity, 'unit' => $unit, 'category' => $category, 'group' => 'Comida', 'page' => 1, 'line' => 10];
    }

    private function report(array $rows, string $restaurant = 'Local A'): ConsumoImport
    {
        return ConsumoImport::create(['file_name' => 'consumo.pdf', 'original_path' => 'fake.pdf', 'row_count' => count($rows),
            'data' => ['metadata' => ['restaurant' => $restaurant], 'rows' => $rows], 'created_by' => auth()->id()]);
    }

    private function prepare(array $document, ConsumoImport $import): array
    {
        return $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', ['document_id' => $document['id'], 'document_version' => $document['version']])
            ->assertOk()->json();
    }

    private function complete(array $document, ConsumoImport $import, array $links): array
    {
        return $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', ['document_id' => $document['id'], 'document_version' => $document['version'], 'links' => $links])
            ->assertCreated()->json();
    }

    private function link(array $product, array $row): array
    {
        return ['product_id' => $product['id'], 'source_ids' => [$row['id']]];
    }

    private function editableDocument(array $document): array
    {
        unset($document['updated_at']);
        foreach ($document['suppliers'] as &$supplier) {
            foreach ($supplier['products'] as &$product) {
                unset($product['consumption']);
            }
            unset($product);
        }
        unset($supplier);

        return $document;
    }

    public function test_editor_saves_a_link_free_document_and_legacy_layout_without_mutating_history(): void
    {
        $source = $this->row('bbq');
        $import = $this->report([$source]);
        $payload = $this->payload([$this->product('Barbacoa')]);
        $legacyProducts = $payload['suppliers'];
        $legacyProducts[0]['products'][0]['source_ids'] = [$source['id']];
        $history = ConsumoSheet::create(['title' => 'Anterior', 'import_id' => $import->id,
            'payload' => ['suppliers' => $legacyProducts, 'grid_html' => $this->grid($legacyProducts)], 'version' => 1]);
        $before = $history->fresh()->toArray();
        $fallback = $this->getJson('/admin/consumos/documento')->assertOk()->json('document');
        $this->assertNull($fallback['id']);
        $this->assertSame([], $fallback['suppliers'][0]['products'][0]['source_ids']);
        $this->assertNull($fallback['suppliers'][0]['products'][0]['consumption']);
        $this->assertStringNotContainsString($source['id'], $fallback['grid_html']);
        $document = $this->saveDocument($this->editableDocument($fallback));
        $this->assertStringNotContainsString('999 inventado', $document['grid_html']);
        $this->assertDatabaseCount('consumo_product_links', 0);
        $this->assertSame($before, $history->fresh()->toArray());
        $forged = $this->editableDocument($document);
        $forged['suppliers'][0]['products'][0]['source_ids'] = [$source['id']];
        $this->postJson('/admin/consumos/documento', $forged)->assertUnprocessable();
    }

    public function test_first_manual_link_is_reused_on_new_reports_with_exact_new_quantities_and_new_scoped_ids(): void
    {
        $product = $this->product('Barbacoa');
        $document = $this->saveDocument($this->payload([$product]));
        $firstRow = $this->row('bbq', quantity: '123456789123456789.1234');
        $first = $this->report([$firstRow]);
        $prepared = $this->prepare($document, $first);
        $this->assertSame([$product['id']], $prepared['pending_product_ids']); // Same name never guesses the first link.
        $result = $this->complete($document, $first, [$this->link($product, $firstRow)]);
        $oldSheet = ConsumoSheet::findOrFail($result['sheet']['id'])->toArray();
        $this->assertSame('123456789123456789.1234', $result['sheet']['suppliers'][0]['products'][0]['consumption']['amount']);
        $secondRow = $this->row('bbq', quantity: '0.000');
        $second = $this->report([$this->row('other', 'Otro', '9.999'), $secondRow]);
        $prepared = $this->prepare($result['document'], $second);
        $this->assertSame([], $prepared['pending']);
        $this->assertSame([$secondRow['id']], $prepared['sheet']['suppliers'][0]['products'][0]['source_ids']);
        $this->assertSame('0.000', $prepared['sheet']['suppliers'][0]['products'][0]['consumption']['amount']);
        $completed = $this->complete($result['document'], $second, []);
        $this->assertNotSame($result['sheet']['id'], $completed['sheet']['id']);
        $this->assertDatabaseCount('consumo_sheets', 2);
        $this->assertSame($oldSheet, ConsumoSheet::findOrFail($result['sheet']['id'])->toArray());
        $this->assertSame([$firstRow], $first->fresh()->data['rows']);
        $this->assertSame([], $completed['document']['suppliers'][0]['products'][0]['source_ids']);
        $this->postJson('/admin/consumos/importaciones/'.$second->id.'/completar', ['document_id' => $result['document']['id'], 'document_version' => $result['document']['version'], 'links' => []])
            ->assertOk()->assertJsonPath('sheet.id', $completed['sheet']['id']);
        $this->assertDatabaseCount('consumo_sheets', 2);
    }

    public function test_added_blank_typed_row_is_pending_and_source_selection_fills_its_durable_name(): void
    {
        $known = $this->product('BBQ');
        $document = $this->saveDocument($this->payload([$known]));
        $row = $this->row('bbq');
        $first = $this->complete($document, $this->report([$row]), [$this->link($known, $row)]);
        $edit = $this->editableDocument($first['document']);
        $new = $this->product('');
        $edit['suppliers'][0]['products'][] = $new;
        $edit['grid_html'] = $this->grid($edit['suppliers']);
        $document = $this->saveDocument($edit);
        $rows = [$this->row('bbq'), $this->row('beer', 'Cerveza', '7.000', 'UD')];
        $report = $this->report($rows);
        $prepared = $this->prepare($document, $report);
        $this->assertSame([$new['id']], $prepared['pending_product_ids']);
        $completed = $this->complete($document, $report, [$this->link($new, $rows[1])]);
        $this->assertSame('Cerveza', $completed['document']['suppliers'][0]['products'][1]['name']);
        $next = $this->report([$this->row('beer', 'Cerveza', '2.000', 'UD'), $this->row('bbq')]);
        $this->assertSame([], $this->prepare($completed['document'], $next)['pending']);
    }

    public function test_context_distinguishes_same_names_and_indistinguishable_duplicates_always_stay_manual(): void
    {
        $product = $this->product('Producto');
        $document = $this->saveDocument($this->payload([$product]));
        $row = $this->row('a', 'Cuatro quesos', category: 'Ingredientes');
        $other = $this->row('b', 'Cuatro quesos', category: 'Pizzas');
        $first = $this->complete($document, $this->report([$row, $other]), [$this->link($product, $row)]);
        $prepared = $this->prepare($first['document'], $this->report([$this->row('b', 'Cuatro quesos', category: 'Pizzas'), $this->row('a', 'Cuatro quesos', category: 'Ingredientes')]));
        $this->assertSame([], $prepared['pending']);
        $duplicates = [$this->row('a', 'Cuatro quesos', category: 'Ingredientes'), $this->row('a-2', 'Cuatro quesos', category: 'Ingredientes')];
        $report = $this->report($duplicates);
        $this->assertSame('ambiguous', $this->prepare($first['document'], $report)['pending'][0]['reason']);
        $second = $this->complete($first['document'], $report, [$this->link($product, $duplicates[0])]);
        $this->assertSame('ambiguous', $this->prepare($second['document'], $this->report(array_reverse($duplicates)))['pending'][0]['reason']);
        // A once-ambiguous occurrence is not trusted merely because its twin disappears.
        $this->assertSame('ambiguous', $this->prepare($second['document'], $this->report([$this->row('a', 'Cuatro quesos', category: 'Ingredientes')]))['pending'][0]['reason']);
    }

    public function test_missing_or_changed_source_requires_explicit_skip_without_forgetting_previous_mapping(): void
    {
        $product = $this->product('BBQ');
        $document = $this->saveDocument($this->payload([$product]));
        $row = $this->row('bbq');
        $first = $this->complete($document, $this->report([$row]), [$this->link($product, $row)]);
        $originalLink = ConsumoProductLink::firstOrFail()->sources;
        foreach ([$this->report([$this->row('bbq', unit: 'UD')]), $this->report([$this->row('bbq')], 'Otro local'), $this->report([$this->row('changed')])] as $report) {
            $prepared = $this->prepare($first['document'], $report);
            $this->assertSame('source_missing', $prepared['pending'][0]['reason']);
            $this->assertNull($prepared['sheet']['suppliers'][0]['products'][0]['consumption']);
        }
        $missing = $this->report([$this->row('other', 'Otro')]);
        $this->postJson('/admin/consumos/importaciones/'.$missing->id.'/completar', ['document_id' => $first['document']['id'], 'document_version' => $first['document']['version'], 'links' => []])->assertUnprocessable();
        $skipped = $this->complete($first['document'], $missing, [['product_id' => $product['id'], 'source_ids' => [], 'skip' => true]]);
        $this->assertNull($skipped['sheet']['suppliers'][0]['products'][0]['consumption']);
        $this->assertSame($originalLink, ConsumoProductLink::firstOrFail()->sources);
        $this->assertSame([], $this->prepare($skipped['document'], $this->report([$this->row('bbq')]))['pending']);
    }

    public function test_invalid_manual_choices_and_stale_documents_never_partially_save_links_or_sheets(): void
    {
        $products = [$this->product('A'), $this->product('B')];
        $document = $this->saveDocument($this->payload($products));
        $rows = [$this->row('a'), $this->row('b', 'Otra', unit: 'UD')];
        $import = $this->report($rows);
        $base = ['document_id' => $document['id'], 'document_version' => $document['version']];
        foreach ([
            [$this->link($products[0], $rows[0]), $this->link($products[1], $rows[0])],
            [['product_id' => $products[0]['id'], 'source_ids' => [$rows[0]['id'], $rows[1]['id']]], ['product_id' => $products[1]['id'], 'source_ids' => [], 'skip' => true]],
            [['product_id' => $products[0]['id'], 'source_ids' => ['invented']]],
        ] as $links) {
            $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [...$base, 'links' => $links])->assertUnprocessable();
            $this->assertDatabaseCount('consumo_product_links', 0);
            $this->assertDatabaseCount('consumo_sheets', 0);
            $this->assertSame(1, ConsumoDocument::firstOrFail()->version);
        }
        $edit = $this->editableDocument($document);
        $edit['title'] = 'Documento nuevo';
        $updated = $this->saveDocument($edit);
        $this->postJson('/admin/consumos/documento', $edit)->assertStatus(409);
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', $base)->assertStatus(409);
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [...$base, 'links' => [$this->link($products[0], $rows[0]), $this->link($products[1], $rows[1])]])->assertStatus(409);
        $this->assertSame($updated['version'], ConsumoDocument::firstOrFail()->version);
        $this->assertDatabaseCount('consumo_sheets', 0);
    }

    public function test_repurposed_or_deleted_product_uuid_cannot_reuse_its_old_link(): void
    {
        $product = $this->product('BBQ');
        $document = $this->saveDocument($this->payload([$product]));
        $row = $this->row('bbq');
        $first = $this->complete($document, $this->report([$row]), [$this->link($product, $row)]);
        $edit = $this->editableDocument($first['document']);
        $edit['suppliers'][0]['products'][0]['name'] = 'Otro producto';
        $edit['grid_html'] = $this->grid($edit['suppliers']);
        $updated = $this->saveDocument($edit);
        $this->assertDatabaseCount('consumo_product_links', 0);
        $this->assertSame([$product['id']], $this->prepare($updated, $this->report([$this->row('bbq')]))['pending_product_ids']);
        $this->assertSame('BBQ', ConsumoSheet::firstOrFail()->payload['suppliers'][0]['products'][0]['name']);
    }

    private function pagedPayload(array $groups): array
    {
        $suppliers = $pages = [];
        foreach ($groups as $name => $products) {
            $supplier = ['id' => (string) Str::uuid(), 'name' => $name, 'products' => $products];
            $suppliers[] = $supplier;
            $pages[] = ['id' => (string) Str::uuid(), 'title' => $name, 'grid_html' => $this->grid([$supplier])];
        }

        return ['title' => 'Documento de varias hojas', 'suppliers' => $suppliers, 'pages' => $pages, 'grid_html' => $pages[0]['grid_html']];
    }

    public function test_all_pages_round_trip_and_quantities_are_derived_from_explicit_per_page_links(): void
    {
        $front = $this->product('Lata Amstel');
        $back = $this->product('Cerveza');
        $payload = $this->pagedPayload(['Portada' => [$front], 'GM' => [$back], 'Mercadona' => [$this->product('Sin consumo')]]);
        $payload['pages'][1]['grid_html'] .= '<tr><td><script>alert(1)</script><b>Nota segunda hoja</b></td></tr>';
        $document = $this->saveDocument($payload);
        $this->assertCount(3, $document['pages']);
        $this->assertSame(array_column($payload['pages'], 'id'), array_column($document['pages'], 'id'));
        $this->assertSame($document['pages'][0]['grid_html'], $document['grid_html']);
        $this->assertStringNotContainsString('<script', $document['pages'][1]['grid_html']);
        $this->assertStringContainsString('Nota segunda hoja', $document['pages'][1]['grid_html']);
        foreach ($document['pages'] as $page) {
            $this->assertStringNotContainsString('999 inventado', $page['grid_html']);
        }
        $row = $this->row('beer', 'Cerveza', '146.000', 'UD');
        $import = $this->report([$row]);
        $prepared = $this->prepare($document, $import);
        $this->assertSame(array_column($payload['pages'], 'id'), array_column($prepared['pending'], 'page_id'));
        $completed = $this->complete($document, $import, [
            $this->link($front, $row), $this->link($back, $row),
            ['product_id' => $payload['suppliers'][2]['products'][0]['id'], 'source_ids' => [], 'skip' => true],
        ]);
        $this->assertSame('146.000', $completed['sheet']['suppliers'][0]['products'][0]['consumption']['amount']);
        $this->assertSame('146.000', $completed['sheet']['suppliers'][1]['products'][0]['consumption']['amount']);
        $this->assertNull($completed['sheet']['suppliers'][2]['products'][0]['consumption']);
        foreach ([0, 1] as $index) {
            $this->assertStringContainsString('146 UD', $completed['sheet']['pages'][$index]['grid_html']);
            $this->assertStringNotContainsString('146 UD', $completed['document']['pages'][$index]['grid_html']);
        }
        $this->getJson('/admin/consumos/fichas/'.$completed['sheet']['id'])->assertOk()->assertExactJson(['sheet' => $completed['sheet']]);
        $this->assertSame([$row], $import->fresh()->data['rows']);
    }

    public function test_reusing_an_import_after_document_changes_creates_a_new_immutable_snapshot_and_retries_are_idempotent(): void
    {
        $product = $this->product('Cerveza');
        $document = $this->saveDocument($this->pagedPayload(['Portada' => [$product]]));
        $row = $this->row('beer', 'Cerveza', '146.000', 'UD');
        $import = $this->report([$row]);
        $first = $this->complete($document, $import, [$this->link($product, $row)]);
        $before = ConsumoSheet::findOrFail($first['sheet']['id'])->toArray();
        $edit = $this->editableDocument($first['document']);
        $new = $this->product('Otro bloque de cerveza');
        $supplier = ['id' => (string) Str::uuid(), 'name' => 'GM', 'products' => [$new]];
        $edit['suppliers'][] = $supplier;
        $edit['pages'][] = ['id' => (string) Str::uuid(), 'title' => 'Hoja trasera', 'grid_html' => $this->grid([$supplier])];
        $updated = $this->saveDocument($edit);
        $prepared = $this->prepare($updated, $import);
        $this->assertSame([$new['id']], $prepared['pending_product_ids']);
        $second = $this->complete($updated, $import, [$this->link($new, $row)]);
        $this->assertNotSame($first['sheet']['id'], $second['sheet']['id']);
        $this->assertCount(2, $second['sheet']['pages']);
        $this->assertSame($before, ConsumoSheet::findOrFail($first['sheet']['id'])->toArray());
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            'document_id' => $updated['id'], 'document_version' => $updated['version'], 'links' => [$this->link($new, $row)],
        ])->assertOk()->assertJsonPath('sheet.id', $second['sheet']['id']);
        $this->assertDatabaseCount('consumo_sheets', 2);
        $this->assertSame($updated['version'], ConsumoSheet::findOrFail($second['sheet']['id'])->document_version);
    }

    public function test_same_page_duplicate_sources_and_cross_page_split_product_cells_are_rejected(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $payload = $this->pagedPayload(['Principal' => [$a, $b], 'Trasera' => [$this->product('C')]]);
        $document = $this->saveDocument($payload);
        $row = $this->row('beer', 'Cerveza', '146.000', 'UD');
        $import = $this->report([$row]);
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            'document_id' => $document['id'], 'document_version' => $document['version'],
            'links' => [$this->link($a, $row), $this->link($b, $row), ['product_id' => $payload['suppliers'][1]['products'][0]['id'], 'source_ids' => [], 'skip' => true]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('consumo_sheets', 0);
        $this->assertDatabaseCount('consumo_product_links', 0);
        $edit = $this->editableDocument($document);
        preg_match('/<td[^>]*data-consumo-field="stock"[^>]*>.*?<\/td>/', $edit['pages'][0]['grid_html'], $cell);
        $edit['pages'][0]['grid_html'] = str_replace($cell[0], '', $edit['pages'][0]['grid_html']);
        $edit['grid_html'] = $edit['pages'][0]['grid_html'];
        $edit['pages'][1]['grid_html'] .= '<tr>'.$cell[0].'</tr>';
        $this->postJson('/admin/consumos/documento', $edit)->assertUnprocessable()->assertJsonValidationErrors('grid_html');
        $this->assertSame($document['pages'], $this->getJson('/admin/consumos/documento')->json('document.pages'));
    }

    public function test_legacy_clients_cannot_silently_remove_other_pages_and_page_identifiers_are_unique(): void
    {
        $payload = $this->pagedPayload(['Primera' => [$this->product('A')], 'Segunda' => [$this->product('B')]]);
        $document = $this->saveDocument($payload);
        $edit = $this->editableDocument($document);
        unset($edit['pages']);
        $edit['suppliers'] = [$edit['suppliers'][0]];
        $this->postJson('/admin/consumos/documento', $edit)->assertUnprocessable()->assertJsonValidationErrors('pages');
        $edit = $this->editableDocument($document);
        $edit['pages'][1]['id'] = $edit['pages'][0]['id'];
        $this->postJson('/admin/consumos/documento', $edit)->assertUnprocessable()->assertJsonValidationErrors('pages.0.id');
        $this->assertSame($document['pages'], $this->getJson('/admin/consumos/documento')->json('document.pages'));
    }

    public function test_review_all_requires_fresh_manual_choices_without_changing_remembered_links_until_completion(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$a], 'Segunda' => [$b]]));
        $rows = [$this->row('a', 'Origen A', '1.000'), $this->row('b', 'Origen B', '2.000')];
        $first = $this->complete($document, $this->report($rows), [$this->link($a, $rows[0]), $this->link($b, $rows[1])]);
        $remembered = ConsumoProductLink::orderBy('id')->get()->toArray();
        $nextRows = [$this->row('a', 'Origen A', '3.000'), $this->row('b', 'Origen B', '4.000')];
        $nextImport = $this->report($nextRows);
        $base = ['document_id' => $first['document']['id'], 'document_version' => $first['document']['version'], 'review_all' => true];
        $prepared = $this->postJson('/admin/consumos/importaciones/'.$nextImport->id.'/preparar', $base)->assertOk()->json();
        $this->assertSame([$a['id'], $b['id']], $prepared['pending_product_ids']);
        $this->assertSame(['manual_review', 'manual_review'], array_column($prepared['pending'], 'reason'));
        foreach ($prepared['sheet']['suppliers'] as $supplier) {
            $this->assertSame([], $supplier['products'][0]['source_ids']);
            $this->assertNull($supplier['products'][0]['consumption']);
        }
        $this->assertSame($remembered, ConsumoProductLink::orderBy('id')->get()->toArray());
        $this->postJson('/admin/consumos/importaciones/'.$nextImport->id.'/completar', [...$base, 'links' => [$this->link($a, $nextRows[1])]])
            ->assertUnprocessable()->assertJsonValidationErrors('links');
        $this->assertSame($remembered, ConsumoProductLink::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('consumo_sheets', 1);
        $this->assertSame([], $this->prepare($first['document'], $nextImport)['pending']); // Default reuse remains available.
        $completed = $this->postJson('/admin/consumos/importaciones/'.$nextImport->id.'/completar', [
            ...$base, 'links' => [$this->link($a, $nextRows[1]), $this->link($b, $nextRows[0])],
        ])->assertCreated()->json();
        $this->assertSame('4.000', $completed['sheet']['suppliers'][0]['products'][0]['consumption']['amount']);
        $this->assertSame('3.000', $completed['sheet']['suppliers'][1]['products'][0]['consumption']['amount']);
        $this->assertDatabaseCount('consumo_sheets', 2);
    }

    public function test_review_all_remembers_explicit_blank_by_removing_older_mapping_atomically(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$a], 'Segunda' => [$b]]));
        $rows = [$this->row('a', 'Origen A', '1.000'), $this->row('b', 'Origen B', '2.000')];
        $import = $this->report($rows);
        $first = $this->complete($document, $import, [$this->link($a, $rows[0]), $this->link($b, $rows[1])]);
        $oldSnapshot = ConsumoSheet::findOrFail($first['sheet']['id'])->toArray();
        $remembered = ConsumoProductLink::orderBy('id')->get()->toArray();
        $base = ['document_id' => $first['document']['id'], 'document_version' => $first['document']['version'], 'review_all' => true];
        $blankA = ['product_id' => $a['id'], 'source_ids' => [], 'skip' => true];

        // An incomplete review cannot erase even the first explicitly skipped map.
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [...$base, 'links' => [$blankA]])
            ->assertUnprocessable()->assertJsonValidationErrors('links');
        $this->assertSame($remembered, ConsumoProductLink::orderBy('id')->get()->toArray());

        $completed = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'links' => [$blankA, $this->link($b, $rows[1])],
        ])->assertCreated()->json();
        $this->assertNull($completed['sheet']['suppliers'][0]['products'][0]['consumption']);
        $this->assertSame('2.000', $completed['sheet']['suppliers'][1]['products'][0]['consumption']['amount']);
        $this->assertDatabaseMissing('consumo_product_links', ['document_id' => $document['id'], 'product_id' => $a['id']]);
        $this->assertDatabaseHas('consumo_product_links', ['document_id' => $document['id'], 'product_id' => $b['id']]);
        $this->assertSame($oldSnapshot, ConsumoSheet::findOrFail($first['sheet']['id'])->toArray());
        $next = $this->prepare($completed['document'], $import);
        $this->assertSame([$a['id']], $next['pending_product_ids']);
        $this->assertNull($next['sheet']['suppliers'][0]['products'][0]['consumption']);
        $this->assertSame([], $next['sheet']['suppliers'][0]['products'][0]['source_ids']);
        $this->assertSame('2.000', $next['sheet']['suppliers'][1]['products'][0]['consumption']['amount']);
    }

    public function test_selected_second_page_limits_pending_and_snapshot_without_losing_other_template_pages(): void
    {
        $excluded = $this->product('Primera sin revisar');
        $selected = $this->product('');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$excluded], 'Segunda' => [$selected]]));
        $row = $this->row('selected', 'Producto segunda hoja', '7.000');
        $import = $this->report([$row]);
        $base = ['document_id' => $document['id'], 'document_version' => $document['version'],
            'review_all' => true, 'page_ids' => [$document['pages'][1]['id']]];
        $prepared = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', $base)->assertOk()->json();
        $this->assertSame($base['page_ids'], $prepared['page_ids']);
        $this->assertSame($base['page_ids'], $prepared['sheet']['page_ids']);
        $this->assertSame([$selected['id']], $prepared['pending_product_ids']);
        $this->assertSame($base['page_ids'], array_column($prepared['sheet']['pages'], 'id'));
        $this->assertSame([$document['suppliers'][1]['id']], array_column($prepared['sheet']['suppliers'], 'id'));
        $this->assertCount(2, $prepared['document']['pages']);
        // An excluded product cannot be submitted through a forged link.
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'links' => [$this->link($excluded, $row), $this->link($selected, $row)],
        ])->assertUnprocessable()->assertJsonValidationErrors('links.0.product_id');
        $completed = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'links' => [$this->link($selected, $row)],
        ])->assertCreated()->json();
        $this->assertSame($base['page_ids'], array_column($completed['sheet']['pages'], 'id'));
        $this->assertSame([$document['suppliers'][1]['id']], array_column($completed['sheet']['suppliers'], 'id'));
        $this->assertSame('7.000', $completed['sheet']['suppliers'][0]['products'][0]['consumption']['amount']);
        $this->assertCount(2, $completed['document']['pages']);
        $this->assertSame($document['suppliers'][0], $completed['document']['suppliers'][0]);
        $this->assertSame($document['pages'][0], $completed['document']['pages'][0]);
        $this->assertSame('Producto segunda hoja', $completed['document']['suppliers'][1]['products'][0]['name']);
        $this->assertDatabaseMissing('consumo_product_links', ['product_id' => $excluded['id']]);
    }

    public function test_two_selected_pages_keep_saved_order_and_allow_explicit_source_reuse_across_them(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $c = $this->product('C');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$a], 'Segunda' => [$b], 'Tercera' => [$c]]));
        $row = $this->row('beer', 'Cerveza', '5.000', 'UD');
        $import = $this->report([$row]);
        $base = ['document_id' => $document['id'], 'document_version' => $document['version'], 'review_all' => true,
            'page_ids' => [$document['pages'][2]['id'], strtoupper($document['pages'][0]['id'])]];
        $expectedPages = [$document['pages'][0]['id'], $document['pages'][2]['id']];
        $prepared = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', $base)->assertOk()->json();
        $this->assertSame($expectedPages, $prepared['page_ids']);
        $this->assertSame([$a['id'], $c['id']], $prepared['pending_product_ids']);
        $this->assertSame(['manual_review', 'manual_review'], array_column($prepared['pending'], 'reason'));
        $completed = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'links' => [$this->link($a, $row), $this->link($c, $row)],
        ])->assertCreated()->json();
        $this->assertSame($expectedPages, array_column($completed['sheet']['pages'], 'id'));
        $this->assertCount(2, $completed['sheet']['suppliers']);
        foreach ($completed['sheet']['suppliers'] as $supplier) {
            $this->assertSame('5.000', $supplier['products'][0]['consumption']['amount']);
        }
        $this->assertCount(3, $completed['document']['pages']);
    }

    public function test_manual_review_of_selected_page_preserves_excluded_remembered_links_and_layout(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$a], 'Segunda' => [$b]]));
        $rows = [$this->row('a', 'Origen A'), $this->row('b', 'Origen B')];
        $first = $this->complete($document, $this->report($rows), [$this->link($a, $rows[0]), $this->link($b, $rows[1])]);
        $excludedLink = ConsumoProductLink::where('product_id', $b['id'])->firstOrFail()->toArray();
        $import = $this->report([$this->row('b', 'Origen B', '9.000')]);
        $base = ['document_id' => $first['document']['id'], 'document_version' => $first['document']['version'],
            'review_all' => true, 'page_ids' => [$document['pages'][0]['id']]];
        $prepared = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', $base)->assertOk()->json();
        $this->assertSame([$a['id']], $prepared['pending_product_ids']);
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [...$base, 'links' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('links');
        $completed = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'links' => [['product_id' => $a['id'], 'source_ids' => [], 'skip' => true]],
        ])->assertCreated()->json();
        $this->assertDatabaseMissing('consumo_product_links', ['product_id' => $a['id']]);
        $this->assertSame($excludedLink, ConsumoProductLink::where('product_id', $b['id'])->firstOrFail()->toArray());
        $this->assertSame($first['document']['pages'][1], $completed['document']['pages'][1]);
        $this->assertSame($first['document']['suppliers'][1], $completed['document']['suppliers'][1]);
        $this->assertCount(1, $completed['sheet']['pages']);
        $next = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', [
            'document_id' => $completed['document']['id'], 'document_version' => $completed['document']['version'],
            'page_ids' => [$document['pages'][1]['id']],
        ])->assertOk()->json();
        $this->assertSame([], $next['pending']);
        $this->assertSame('9.000', $next['sheet']['suppliers'][0]['products'][0]['consumption']['amount']);
    }

    public function test_empty_duplicate_unknown_and_malformed_page_selections_are_rejected_without_changes(): void
    {
        $product = $this->product('A');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$product], 'Segunda' => [$this->product('B')]]));
        $row = $this->row('a');
        $import = $this->report([$row]);
        $base = ['document_id' => $document['id'], 'document_version' => $document['version']];
        foreach ([[], null, [(string) Str::uuid()], ['not-a-uuid'], [$document['pages'][0]['id'], strtoupper($document['pages'][0]['id'])]] as $ids) {
            foreach (['preparar', 'completar'] as $action) {
                $this->postJson('/admin/consumos/importaciones/'.$import->id.'/'.$action, [
                    ...$base, 'page_ids' => $ids, 'links' => [$this->link($product, $row)],
                ])->assertUnprocessable();
            }
        }
        $this->assertDatabaseCount('consumo_sheets', 0);
        $this->assertDatabaseCount('consumo_product_links', 0);
        $this->assertSame($document['version'], ConsumoDocument::firstOrFail()->version);
    }

    public function test_page_scope_is_part_of_idempotent_retry_and_survives_ficha_edits(): void
    {
        $a = $this->product('A');
        $b = $this->product('B');
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$a], 'Segunda' => [$b]]));
        $rows = [$this->row('a', 'Origen A'), $this->row('b', 'Origen B')];
        $import = $this->report($rows);
        $base = ['document_id' => $document['id'], 'document_version' => $document['version']];
        $completed = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'page_ids' => [$document['pages'][0]['id']], 'links' => [$this->link($a, $rows[0])],
        ])->assertCreated()->json();
        $editable = $this->editableDocument($completed['sheet']);
        $edited = $this->postJson('/admin/consumos/fichas', [
            'id' => $completed['sheet']['id'], 'version' => $completed['sheet']['version'], 'title' => 'Ficha editada',
            'import_id' => $import->id, 'suppliers' => $editable['suppliers'], 'pages' => $completed['sheet']['pages'],
        ])->assertOk()->json('sheet');
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            ...$base, 'page_ids' => [strtoupper($document['pages'][0]['id'])], 'links' => [],
        ])->assertOk()->assertJsonPath('sheet.id', $edited['id'])->assertJsonPath('sheet.title', 'Ficha editada');
        foreach ([null, [$document['pages'][1]['id']], array_column($document['pages'], 'id')] as $ids) {
            $request = [...$base, 'links' => [$this->link($b, $rows[1])]];
            if ($ids !== null) {
                $request['page_ids'] = $ids;
            }
            $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', $request)->assertStatus(409);
        }
        $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', [
            ...$base, 'page_ids' => [$document['pages'][1]['id']],
        ])->assertStatus(409);
        $second = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [
            'document_id' => $completed['document']['id'], 'document_version' => $completed['document']['version'],
            'page_ids' => [$document['pages'][1]['id']], 'links' => [$this->link($b, $rows[1])],
        ])->assertCreated()->json();
        $this->assertNotSame($completed['sheet']['id'], $second['sheet']['id']);
        $this->assertSame([$document['pages'][1]['id']], array_column($second['sheet']['pages'], 'id'));
        $this->assertDatabaseCount('consumo_sheets', 2);
        $this->assertDatabaseCount('consumo_product_links', 2);
    }

    public function test_selecting_a_page_keeps_empty_supplier_tables_and_untyped_content(): void
    {
        $document = $this->saveDocument($this->pagedPayload(['Primera' => [$this->product('A')], 'Embalajes' => []]));
        $import = $this->report([$this->row('a')]);
        $base = ['document_id' => $document['id'], 'document_version' => $document['version'],
            'page_ids' => [$document['pages'][1]['id']]];
        $prepared = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/preparar', $base)->assertOk()->json();
        $this->assertSame([], $prepared['pending']);
        $this->assertSame([$document['suppliers'][1]['id']], array_column($prepared['sheet']['suppliers'], 'id'));
        $this->assertSame([], $prepared['sheet']['suppliers'][0]['products']);
        $completed = $this->postJson('/admin/consumos/importaciones/'.$import->id.'/completar', [...$base, 'links' => []])
            ->assertCreated()->json();
        $this->assertSame($prepared['sheet']['pages'], $completed['sheet']['pages']);
        $this->assertCount(2, $completed['document']['pages']);
    }
}
