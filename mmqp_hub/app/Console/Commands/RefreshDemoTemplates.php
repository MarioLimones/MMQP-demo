<?php

namespace App\Console\Commands;

use App\Models\ConsumoDocument;
use App\Models\User;
use App\Services\DemoTemplateExamples;
use App\Support\ArqueoDocument;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshDemoTemplates extends Command
{
    protected $signature = 'demo:plantillas';
    protected $description = 'Amplía los ejemplos iniciales de la demo sin borrar cambios de los visitantes.';

    public function handle(DemoTemplateExamples $examples, ArqueoDocument $documents): int
    {
        if (! config('demo.enabled') || ! Schema::hasTable('mmqp_demo_state') || ! DB::table('mmqp_demo_state')->where('key', 'seed_version')->exists()) {
            $this->error('Esta operación requiere una instalación identificada como demo.');
            return self::FAILURE;
        }
        $admin = User::where('email', 'admin@mmqp.example')->where('es_admin', true)->first();
        $employee = User::where('email', 'empleado@mmqp.example')->first();
        if (! $admin || ! $employee) {
            $this->error('No se han encontrado las cuentas de ejemplo. No se ha cambiado nada.');
            return self::FAILURE;
        }
        $today = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $template = $documents->read(storage_path('app/arqueo_template.json'));
        $legacy = '<tr><td colspan="4"><div class="cell-content">ARQUEO DEMO · Datos ficticios</div></td></tr>'
            .'<tr><td><div class="cell-content">Fecha</div></td><td colspan="3" data-bound-date="true"><div class="cell-content"></div></td></tr>'
            .'<tr><td><div class="cell-content">Empleado</div></td><td data-bound-emp="'.$employee->id.'" data-bound-prop="nombre"><div class="cell-content"></div></td>'
            .'<td><div class="cell-content">Importe de ejemplo</div></td><td data-arqueo-input="true"><div class="cell-content"></div></td></tr>'
            .'<tr><td colspan="2"><div class="cell-content">Observaciones</div></td><td colspan="2" data-arqueo-input="true"><div class="cell-content"></div></td></tr>';
        $legacy = $documents->definition($legacy);
        if (($template['html'] ?? null) === $legacy) {
            $daily = $documents->read(storage_path('app/arqueos/'.$today->toDateString().'.json'));
            $originalValues = ['2-3' => '125,00', '3-1' => 'Ejemplo ficticio para probar edición e impresión.'];
            $replaceDaily = ! $daily || (($daily['html'] ?? null) === $legacy && ($daily['values'] ?? []) === $originalValues);
            $examples->installArqueo(User::all(), $today, $replaceDaily);
            $this->info('Arqueo: plantilla de ejemplo ampliada.');
        } else {
            $this->info('Arqueo: se conserva la plantilla actual o ya actualizada.');
        }
        $document = ConsumoDocument::where('scope', 'default')->first();
        $payload = $document?->payload ?? [];
        if ($document && $document->version === 1 && $document->title === 'DEMO · Documento de consumos'
            && count($payload['suppliers'] ?? []) === 1
            && ($payload['suppliers'][0]['name'] ?? '') === 'Proveedor DEMO'
            && count($payload['suppliers'][0]['products'] ?? []) === 3
            && str_contains($payload['pages'][0]['grid_html'] ?? '', 'CONSUMOS DEMO · Datos ficticios')) {
            DB::transaction(fn () => $examples->installConsumption($admin, $today, $document));
            $this->info('Consumos: plantilla ampliada y nueva ficha de ejemplo; el historial anterior se conserva.');
        } else {
            $this->info('Consumos: se conserva la plantilla actual o ya actualizada.');
        }
        return self::SUCCESS;
    }
}
