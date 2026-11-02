<?php

namespace App\Services;

use App\Models\ConsumoImport;
use App\Models\ConsumoSheet;

class ConsumoSheetSnapshot
{
    public function make(ConsumoSheet $sheet): array
    {
        $pages = $sheet->payload['pages'] ?? [['id' => '00000000-0000-4000-8000-000000000001', 'title' => 'Hoja 1', 'grid_html' => $sheet->payload['grid_html'] ?? null]];

        return [
            'id' => $sheet->id, 'version' => $sheet->version, 'title' => $sheet->title,
            'import_id' => $sheet->import_id, 'updated_at' => $sheet->updated_at->toISOString(),
            'suppliers' => $this->suppliersWithConsumption($sheet->payload['suppliers'] ?? [], $sheet->report),
            'pages' => $pages, 'grid_html' => $pages[0]['grid_html'] ?? null,
        ];
    }

    public function suppliersWithConsumption(array $suppliers, ?ConsumoImport $import): array
    {
        $rows = collect($import?->data['rows'] ?? [])->keyBy('id')->all();
        foreach ($suppliers as &$supplier) {
            foreach ($supplier['products'] as &$product) {
                $product['consumption'] = null;
                foreach ($product['source_ids'] as $id) {
                    $row = $rows[$id];
                    $previous = $product['consumption'];
                    $product['consumption'] = [
                        'amount' => $this->addDecimal($previous['amount'] ?? '0.000', $row['quantity']),
                        'unit' => $row['unit'],
                        'source_count' => ($previous['source_count'] ?? 0) + 1,
                    ];
                }
            }
            unset($product);
        }
        unset($supplier);

        return $suppliers;
    }

    /** Add exact signed decimal strings without floating point or rounding. */
    private function addDecimal(string $left, string $right): string
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        [$leftWhole, $leftFraction] = explode('.', ltrim($left, '+-'));
        [$rightWhole, $rightFraction] = explode('.', ltrim($right, '+-'));
        $scale = max(strlen($leftFraction), strlen($rightFraction));
        $a = ltrim($leftWhole.str_pad($leftFraction, $scale, '0'), '0') ?: '0';
        $b = ltrim($rightWhole.str_pad($rightFraction, $scale, '0'), '0') ?: '0';
        $negative = $leftNegative;
        if ($leftNegative === $rightNegative) {
            $carry = 0;
            $digits = '';
            for ($i = strlen($a) - 1, $j = strlen($b) - 1; $i >= 0 || $j >= 0 || $carry; $i--, $j--) {
                $sum = ($i >= 0 ? (int) $a[$i] : 0) + ($j >= 0 ? (int) $b[$j] : 0) + $carry;
                $digits = ($sum % 10).$digits;
                $carry = intdiv($sum, 10);
            }
        } else {
            $comparison = strlen($a) <=> strlen($b) ?: strcmp($a, $b);
            if ($comparison < 0) {
                [$a, $b] = [$b, $a];
                $negative = $rightNegative;
            }
            $borrow = 0;
            $digits = '';
            for ($i = strlen($a) - 1, $j = strlen($b) - 1; $i >= 0; $i--, $j--) {
                $difference = (int) $a[$i] - ($j >= 0 ? (int) $b[$j] : 0) - $borrow;
                $borrow = $difference < 0 ? 1 : 0;
                $digits = ($difference < 0 ? $difference + 10 : $difference).$digits;
            }
        }
        $isZero = trim($digits, '0') === '';
        $digits = str_pad(ltrim($digits, '0') ?: '0', $scale + 1, '0', STR_PAD_LEFT);

        return ($negative && ! $isZero ? '-' : '').substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
    }
}
