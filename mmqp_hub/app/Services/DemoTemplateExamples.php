<?php

namespace App\Services;

use App\Models\ConsumoDocument;
use App\Models\ConsumoImport;
use App\Models\ConsumoSheet;
use App\Models\User;
use App\Support\ArqueoDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Hand-authored imaginary examples; no operational templates are read. */
class DemoTemplateExamples
{
    private function cell(string $text = '', int $span = 1, string $attributes = '', bool $heading = false): string
    {
        $style = 'border:1px solid #cbd5e1;padding:8px;vertical-align:middle;'.($heading ? 'background-color:#fef3c7;font-weight:bold;color:#78350f;' : 'background-color:#ffffff;color:#0f172a;');
        return '<td colspan="'.$span.'" style="'.$style.'" '.$attributes.'><div class="cell-content">'.e($text).'</div></td>';
    }

    public function arqueoDefinition(Collection $users): string
    {
        $html = '<tr data-arqueo-columns="[19,13,17,17,16,18]" data-arqueo-rows="[18,14,16,12,16,20,20,20,12,18,18,18,12,44,18]">'.$this->cell('ARQUEO DEMO · Turno de ejemplo', 6, '', true).'</tr>'
            .'<tr>'.$this->cell('Una hoja sencilla para reunir lo que se entrega al terminar el turno.', 6).'</tr>'
            .'<tr>'.$this->cell('Fecha').$this->cell('', 2, 'data-bound-date="true"').$this->cell('Control').$this->cell('Inicio y cierre', 2).'</tr>'
            .'<tr>'.$this->cell('EQUIPO Y ENTREGAS', 6, '', true).'</tr>'
            .'<tr>'.$this->cell('Persona', 2, '', true).$this->cell('Mañana', 1, '', true).$this->cell('Tarde', 1, '', true).$this->cell('Entregado €', 1, '', true).$this->cell('Observaciones', 1, '', true).'</tr>';
        foreach ($users->filter(fn ($user) => in_array($user->email, ['empleado@mmqp.example', 'repartidor01@mmqp.example', 'auxiliar01@mmqp.example'], true))->values() as $user) {
            $html .= '<tr>'.$this->cell('', 2, 'data-bound-emp="'.$user->id.'" data-bound-prop="nombre"')
                .$this->cell('', 1, 'data-bound-emp="'.$user->id.'" data-bound-prop="manana"')
                .$this->cell('', 1, 'data-bound-emp="'.$user->id.'" data-bound-prop="noche"')
                .$this->cell('', 1, 'data-arqueo-input="true"').$this->cell('', 1, 'data-arqueo-input="true"').'</tr>';
        }
        $html .= '<tr>'.$this->cell('RESUMEN DEL TURNO', 6, '', true).'</tr>';
        foreach ([['Efectivo inicial €', 'Efectivo contado €'], ['Cobros con tarjeta €', 'Retiradas de efectivo €'], ['Otros cobros €', 'Total entregado €']] as [$left, $right]) {
            $html .= '<tr>'.$this->cell($left, 2).$this->cell('', 1, 'data-arqueo-input="true"').$this->cell($right, 2).$this->cell('', 1, 'data-arqueo-input="true"').'</tr>';
        }
        $html .= '<tr>'.$this->cell('ALGO QUE CONVIENE RECORDAR', 6, '', true).'</tr>'
            .'<tr>'.$this->cell('', 6, 'data-arqueo-input="true"').'</tr>'
            .'<tr>'.$this->cell('Datos ficticios · Puedes cambiar el diseño, rellenarlo o imprimirlo para probar.', 6).'</tr>';

        return app(ArqueoDocument::class)->definition($html);
    }

    public function installArqueo(Collection $users, CarbonImmutable $today, bool $writeDaily = true): void
    {
        $document = app(ArqueoDocument::class);
        $definition = $this->arqueoDefinition($users);
        $document->save(storage_path('app/arqueo_template.json'), ['html' => $definition, 'demo_example_version' => 2]);
        if (! $writeDaily) { return; }
        $document->save(storage_path('app/arqueos/'.$today->toDateString().'.json'), [
            'html' => $definition, 'demo_example_version' => 2,
            'values' => ['5-3' => '125,00', '5-4' => 'Entrega de prueba', '6-3' => '85,00', '7-3' => '60,00',
                '9-1' => '300,00', '9-3' => '570,00', '10-1' => '180,00', '10-3' => '0,00',
                '11-1' => '0,00', '11-3' => '270,00', '13-0' => 'Ejemplo ficticio: el turno termina con las entregas revisadas.'],
        ]);
    }

    public function installConsumption(User $admin, CarbonImmutable $today, ?ConsumoDocument $existing = null): void
    {
        $suppliers = $templateSuppliers = $rows = [];
        $index = 0;
        foreach ([
            'Despensa del Sol · DEMO' => ['Agua de ejemplo', 'Harina de ejemplo', 'Tomate de ejemplo', 'Aceite de ejemplo'],
            'Envases del Patio · DEMO' => ['Caja pequeña de ejemplo', 'Caja grande de ejemplo', 'Bolsa de papel de ejemplo', 'Servilletas de ejemplo'],
        ] as $name => $names) {
            $supplier = ['id' => (string) Str::uuid(), 'name' => $name, 'products' => []];
            foreach ($names as $productName) {
                $source = 'demo-source-'.$index;
                $supplier['products'][] = ['id' => (string) Str::uuid(), 'name' => $productName,
                    'stock' => (string) (20 + $index), 'pedido' => (string) (5 + $index), 'source_ids' => [$source]];
                $rows[] = ['id' => $source, 'name' => $productName, 'quantity' => (10 + $index).'.000', 'unit' => 'UD'];
                $index++;
            }
            $suppliers[] = $supplier;
            $template = $supplier;
            foreach ($template['products'] as &$product) { $product['source_ids'] = []; $product['stock'] = ''; $product['pedido'] = ''; }
            unset($product);
            $templateSuppliers[] = $template;
        }
        $pageId = (string) Str::uuid();
        $html = '<tr>'.$this->cell('CONSUMOS DEMO · Preparar el próximo pedido', 24, '', true).'</tr>'
            .'<tr>'.$this->cell('Revisa lo consumido, apunta lo que queda y decide qué necesitas pedir. Todos los productos son ficticios.', 24).'</tr>';
        foreach ($suppliers as $supplier) {
            $html .= '<tr>'.$this->cell($supplier['name'], 24, 'data-consumo-provider="'.$supplier['id'].'"', true).'</tr>'
                .'<tr>'.$this->cell('Producto', 12, '', true).$this->cell('Consumido · UD', 4, '', true).$this->cell('Queda · UD', 4, '', true).$this->cell('Pedir · UD', 4, '', true).'</tr>';
            foreach ($supplier['products'] as $product) {
                $html .= '<tr>';
                foreach (['product' => $product['name'], 'consumo' => '', 'stock' => '', 'pedido' => ''] as $field => $value) {
                    $html .= $this->cell($value, $field === 'product' ? 12 : 4, 'data-consumo-product="'.$product['id'].'" data-consumo-supplier="'.$supplier['id'].'" data-consumo-field="'.$field.'"');
                }
                $html .= '</tr>';
            }
        }
        $html = preg_replace('/<td /', '<td rowspan="3" ', $html);
        $html = str_replace('</tr>', '</tr><tr></tr><tr></tr>', $html);
        $grid = app(ConsumoGrid::class);
        $templatePages = $grid->cleanPages([['id' => $pageId, 'title' => 'Pedido de ejemplo', 'grid_html' => $html]], $templateSuppliers, true);
        $payload = ['suppliers' => $templateSuppliers, 'pages' => $templatePages, 'grid_html' => $templatePages[0]['grid_html'], 'demo_example_version' => 2];
        $document = $existing ?: new ConsumoDocument(['scope' => 'default']);
        $document->fill(['title' => 'DEMO · Documento de consumos', 'version' => $existing ? $existing->version + 1 : 1, 'created_by' => $admin->id, 'payload' => $payload])->save();
        $report = ConsumoImport::create(['file_name' => 'ejemplo-ficticio-generado.json', 'original_path' => 'demo/ejemplo-ficticio-generado-v2.json',
            'created_by' => $admin->id, 'row_count' => count($rows),
            'data' => ['metadata' => ['period_start' => $today->subDays(6)->toDateString(), 'period_end' => $today->toDateString(), 'demo' => true], 'rows' => $rows]]);
        Storage::disk('local')->put($report->original_path, json_encode($report->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $displaySuppliers = app(ConsumoSheetSnapshot::class)->suppliersWithConsumption($suppliers, $report);
        $pages = $grid->cleanPages([['id' => $pageId, 'title' => 'Pedido de ejemplo', 'grid_html' => $html]], $displaySuppliers, true);
        $sheet = ConsumoSheet::create(['title' => 'DEMO · Consumos de ejemplo', 'document_id' => $document->id,
            'document_version' => $document->version, 'import_id' => $report->id, 'version' => 1, 'created_by' => $admin->id,
            'payload' => ['suppliers' => $suppliers, 'pages' => $pages, 'grid_html' => $pages[0]['grid_html']]]);
        app(ConsumoHistory::class)->record($sheet, $admin, $today->setTime(10, 0));
    }
}
