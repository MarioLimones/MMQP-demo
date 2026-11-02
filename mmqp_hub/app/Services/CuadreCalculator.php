<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** The existing business formula, evaluated exclusively with integer euro cents. */
class CuadreCalculator
{
    public const BILLETES = [100 => 10000, 50 => 5000, 20 => 2000, 10 => 1000, 5 => 500];

    public const MONEDAS = ['2' => 200, '1' => 100, '0.5' => 50, '0.2' => 20, '0.1' => 10, '0.05' => 5, '0.02' => 2, '0.01' => 1];

    public const MONEY_FIELDS = ['nota_blanca', 'fondo_fijo', 'gastos', 'cambios', 'repartos', 'pedidos_hora_exacta', 'otros', 'venta_parcial'];

    public const MAX_CENTS = 9999999999; // Existing DECIMAL(10,2) storage limit: 99,999,999.99 EUR.

    public const MAX_COUNT = 999999;

    public static function cents(string|int $value): int
    {
        $parts = explode('.', (string) $value);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public function normalize(array $data): array
    {
        foreach (['billetes', 'billetes_ingreso', 'monedas', 'monedas_ingreso'] as $field) {
            $denominations = str_starts_with($field, 'billetes') ? self::BILLETES : self::MONEDAS;
            $data[$field] = array_map(fn ($key) => (int) ($data[$field][$key] ?? 0), array_keys($denominations));
            $data[$field] = array_combine(array_keys($denominations), $data[$field]);
        }
        foreach (self::MONEY_FIELDS as $field) {
            $data[$field] = self::decimal(self::cents($data[$field] ?? '0'));
        }
        $data['blisters'] = ['total' => self::decimal(self::cents($data['blisters']['total']))];
        // These fields never participate in the existing formula.
        $data['blisters_ingreso'] = null;
        $data['expected_version'] = (int) $data['expected_version'];
        $data['motivo'] = $data['motivo'] ?? null;

        return $data;
    }

    public function calculate(array $inputs): array
    {
        $cash = $this->count($inputs['billetes'], self::BILLETES) + $this->count($inputs['monedas'], self::MONEDAS);
        $money = [];
        foreach (self::MONEY_FIELDS as $field) {
            $money[$field] = self::cents($inputs[$field]);
        }
        $blisters = self::cents($inputs['blisters']['total']);
        $additional = array_sum(array_intersect_key($money, array_flip(['gastos', 'cambios', 'repartos', 'pedidos_hora_exacta', 'otros'])));
        $total = $cash + $blisters + $money['nota_blanca'] + ($inputs['turno'] === 'intermedio' ? $additional : 0);
        $ingreso = $inputs['turno'] === 'cierre'
            ? $this->count($inputs['billetes_ingreso'], self::BILLETES) + $this->count($inputs['monedas_ingreso'], self::MONEDAS)
            : 0;
        $remaining = $total - $ingreso;
        $expected = $money['fondo_fijo'] + ($inputs['turno'] === 'intermedio' ? $money['venta_parcial'] : 0);
        $results = ['efectivo_contado' => $cash, 'blisters_total' => $blisters, 'partidas_adicionales' => $additional,
            'total_calculado' => $total, 'ingreso_real' => $ingreso, 'fondo_restante' => $remaining,
            'importe_esperado' => $expected, 'subtotal' => $total - $money['fondo_fijo'], 'descuadre' => $remaining - $expected];
        foreach ($results as $value) {
            if (abs($value) > self::MAX_CENTS) {
                throw ValidationException::withMessages(['billetes' => 'El resultado supera el límite de 99.999.999,99 €. Reduce los importes o las unidades.']);
            }
        }

        return ['formula' => 'cuadre-v1', 'currency' => 'EUR', 'rounding' => 'No rounding: inputs require at most two decimals',
            'inputs' => $inputs, 'input_cents' => $money, 'result_cents' => $results,
            'results' => array_map(self::decimal(...), $results),
            'references' => ['source' => 'employee_declarations', 'internal_movements' => [],
                'fondo_fijo' => $inputs['fondo_fijo'], 'venta_parcial' => $inputs['venta_parcial'],
                'billetes_cents' => self::BILLETES, 'monedas_cents' => self::MONEDAS]];
    }

    private function count(array $counts, array $denominations): int
    {
        $total = 0;
        foreach ($denominations as $key => $cents) {
            $total += $counts[$key] * $cents;
        }

        return $total;
    }
}
