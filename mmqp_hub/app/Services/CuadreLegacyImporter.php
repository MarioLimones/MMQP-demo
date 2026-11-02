<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Preserve the available legacy state without pretending that lost revisions or authors are known. */
class CuadreLegacyImporter
{
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('cuadre_cajas')->where('version', 0)->orderBy('id')->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    if (DB::table('cuadre_revisions')->where('cuadre_id', $record->id)->where('version', 0)->exists()) {
                        continue;
                    }
                    $raw = (array) $record;
                    unset($raw['version'], $raw['calculation_snapshot'], $raw['responsible_id']);
                    $snapshot = ['origin' => 'legacy_import', 'original_attributes' => $raw,
                        'assessment' => $this->assess($raw),
                        'limitations' => 'Autor autenticado desconocido; created_by era un empleado declarado. No se pueden recuperar modificaciones anteriores.'];
                    DB::table('cuadre_revisions')->insert([
                        'cuadre_id' => $record->id, 'version' => 0, 'origin' => 'legacy_import',
                        'actor_id' => null, 'actor_snapshot' => null, 'recorded_at' => now('UTC'),
                        'motivo' => null, 'request_id' => null, 'request_hash' => null,
                        'before_snapshot' => null, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    ]);
                }
            });
        });
    }

    private function assess(array $raw): array
    {
        try {
            $inputs = $raw;
            foreach (['billetes', 'monedas', 'billetes_ingreso', 'monedas_ingreso'] as $field) {
                $inputs[$field] = isset($raw[$field]) ? json_decode($raw[$field], true, 512, JSON_THROW_ON_ERROR) : [];
                $denominations = str_starts_with($field, 'billetes') ? CuadreCalculator::BILLETES : CuadreCalculator::MONEDAS;
                if (! is_array($inputs[$field]) || array_diff(array_keys($inputs[$field]), array_keys($denominations))) {
                    throw new \RuntimeException('Denominaciones antiguas no admitidas.');
                }
                foreach ($inputs[$field] as $key => $value) {
                    if (! is_scalar($value) || ! preg_match('/^\d{1,6}$/D', (string) $value)) {
                        throw new \RuntimeException('Unidades antiguas inválidas.');
                    }
                    if (str_ends_with($field, '_ingreso') && ((int) $value > (int) ($inputs[str_replace('_ingreso', '', $field)][$key] ?? 0) || ($raw['turno'] !== 'cierre' && (int) $value > 0))) {
                        throw new \RuntimeException('Desglose de ingreso antiguo incompatible.');
                    }
                }
            }
            $inputs['blisters'] = isset($raw['blisters']) ? json_decode($raw['blisters'], true, 512, JSON_THROW_ON_ERROR) : ['total' => '0'];
            if (! is_array($inputs['blisters']) || array_diff(array_keys($inputs['blisters']), ['total'])) {
                throw new \RuntimeException('Blisters antiguos sin significado verificable.');
            }
            $inputs['blisters']['total'] = (string) ($inputs['blisters']['total'] ?? '0');
            foreach (CuadreCalculator::MONEY_FIELDS as $field) {
                $inputs[$field] = (string) ($raw[$field] ?? '0');
            }
            foreach ([...CuadreCalculator::MONEY_FIELDS, 'blisters_total'] as $field) {
                $value = $field === 'blisters_total' ? $inputs['blisters']['total'] : $inputs[$field];
                if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
                    throw new \RuntimeException('Importes antiguos inválidos.');
                }
            }
            if (! in_array($raw['turno'], ['apertura', 'intermedio', 'cierre'], true)) {
                throw new \RuntimeException('Turno antiguo no admitido.');
            }
            $inputs['expected_version'] = 0;
            $calculator = new CuadreCalculator;
            $calculated = $calculator->calculate($calculator->normalize($inputs));
            $differences = [];
            foreach (['total_calculado', 'ingreso_real', 'descuadre'] as $field) {
                // Decimal comparison only for diagnosis; original values are never changed.
                if ($raw[$field] === null || (string) $raw[$field] !== $calculated['results'][$field]) {
                    if ($raw[$field] === null || $this->formatOriginal((string) $raw[$field]) !== $calculated['results'][$field]) {
                        $differences[$field] = ['original' => $raw[$field], 'recalculated' => $calculated['results'][$field]];
                    }
                }
            }

            return ['status' => $differences ? 'inconsistent' : 'matches_formula', 'differences' => $differences, 'comparison' => $calculated];
        } catch (Throwable $error) {
            return ['status' => 'invalid_inputs', 'message' => $error->getMessage()];
        }
    }

    /** Format a signed legacy decimal without rounding. */
    private function formatOriginal(string $value): string
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $value, $parts)) {
            return $value;
        }

        return $parts[1].((string) (int) $parts[2]).'.'.str_pad($parts[3] ?? '', 2, '0');
    }
}
