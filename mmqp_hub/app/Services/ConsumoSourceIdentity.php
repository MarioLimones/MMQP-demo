<?php

namespace App\Services;

use App\Models\ConsumoImport;

/** Reuses an explicitly chosen PDF identity only while its context is unambiguous. */
class ConsumoSourceIdentity
{
    public function identity(array $row, ConsumoImport $import): array
    {
        return [
            'restaurant' => $this->normalize($import->data['metadata']['restaurant'] ?? ''),
            'original_source_id' => (string) ($row['original_source_id'] ?? $row['id']),
            'group' => $this->normalize($row['group'] ?? ''),
            'category' => $this->normalize($row['category'] ?? ''),
            'name' => $this->normalize($row['name']),
            // Preserve the report's exact unit label. A changed label requires
            // confirmation and must never silently mix differently typed units.
            'unit' => trim($row['unit']),
        ];
    }

    public function contextKey(array $identity): string
    {
        unset($identity['original_source_id'], $identity['ambiguous']);
        $identity['unit'] = $this->normalize($identity['unit']);

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function key(array $identity): string
    {
        unset($identity['ambiguous']);

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function index(ConsumoImport $import): array
    {
        $rows = $counts = [];
        foreach ($import->data['rows'] as $row) {
            $identity = $this->identity($row, $import);
            $key = $this->contextKey($identity);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $rows[$this->key($identity)] = $row;
        }

        return ['rows' => $rows, 'counts' => $counts];
    }

    public function resolve(array $sources, array $index): array
    {
        $ids = [];
        foreach ($sources as $source) {
            // Occurrence numbers cannot prove which of two identical rows is
            // which after a report changes. Those choices always stay manual.
            if (($source['ambiguous'] ?? false) || ($index['counts'][$this->contextKey($source)] ?? 0) > 1) {
                return ['ids' => [], 'reason' => 'ambiguous'];
            }
            $row = $index['rows'][$this->key($source)] ?? null;
            if (! $row) {
                return ['ids' => [], 'reason' => 'source_missing'];
            }
            $ids[] = $row['id'];
        }

        return ['ids' => $ids, 'reason' => $ids === [] ? 'new_product' : null];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
