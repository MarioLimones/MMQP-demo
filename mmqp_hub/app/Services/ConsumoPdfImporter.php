<?php

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Process\Process;

class ConsumoPdfImporter
{
    private const NUMBER = '[+-]?(?:\d{1,3}(?:\.\d{3})+|\d+),\d+';

    public function import(string $path): array
    {
        if (! is_readable($path) || filesize($path) === 0) {
            throw new RuntimeException('No se puede leer el PDF de consumos.');
        }

        $process = new Process([
            (string) config('consumos.pdf_python'),
            base_path('scripts/extract_consumos_pdf.py'),
            $path,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            report(new RuntimeException('Consumo PDF extraction: '.$process->getErrorOutput()));
            throw new RuntimeException('No se pudo leer el informe. Comprueba que sea un PDF con texto y que el lector de PDF esté instalado.');
        }

        try {
            $pages = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('El lector no ha devuelto un informe válido.', previous: $exception);
        }

        $result = $this->parseExtractedPages($pages, hash_file('sha256', $path));
        if (! $result['coverage']['complete']) {
            throw new RuntimeException($result['coverage']['message']);
        }

        return $result;
    }

    /** Parse all extracted lines without guessing product names or suppliers. */
    public function parseExtractedPages(array $pages, ?string $sha256 = null): array
    {
        $metadata = [
            'title' => null, 'restaurant' => null, 'generated_at' => null,
            'period_start' => null, 'period_end' => null, 'file_sha256' => $sha256,
            'total_value' => null, 'net_sales' => null, 'gross_sales' => null,
            'services_amount' => null, 'net_sales_with_services' => null, 'gross_sales_with_services' => null,
            'totals' => [], 'summary_lines' => [],
        ];
        $rows = $unparsed = $warnings = $pageRows = $occurrences = [];
        $group = $category = null;
        $expectGroup = true;
        $rowCount = 0;
        $categoryValues = $groupValues = $documentValues = [];

        foreach ($pages as $pageIndex => $page) {
            $number = $pageIndex + 1;
            $headerSeen = $footerSeen = false;
            $countBefore = count($rows);
            if (($page['number'] ?? null) !== $number) {
                $warnings[] = 'La secuencia de páginas está incompleta.';
            }
            if (empty($page['lines'])) {
                $warnings[] = "La página {$number} no contiene texto legible; no se ha importado parcialmente.";
            }
            foreach ($page['lines'] ?? [] as $lineIndex => $line) {
                $raw = trim($line['text'] ?? '');
                $source = ['page' => $number, 'line' => $lineIndex + 1, 'raw' => $raw];
                if ($raw === '') {
                    continue;
                }
                if (preg_match('/^Informe de consumos teóricos\s*\(día\/s entero\/s\)$/ui', $raw)) {
                    $metadata['title'] = $raw;

                    continue;
                }
                if (preg_match('/^(.+?)\s+-\s+(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2})$/u', $raw, $match)) {
                    $metadata['restaurant'] = $match[1];
                    $generatedAt = DateTimeImmutable::createFromFormat('!d/m/Y H:i:s', $match[2]);
                    $metadata['generated_at'] = $generatedAt ? $generatedAt->format('Y-m-d H:i:s') : null;

                    continue;
                }
                if (preg_match('/^\(desde (\d{2}\/\d{2}\/\d{4}) hasta (\d{2}\/\d{2}\/\d{4})\)$/u', $raw, $match)) {
                    $metadata['period_start'] = $this->date($match[1]);
                    $metadata['period_end'] = $this->date($match[2]);

                    continue;
                }
                if (preg_match('/^INGREDIENTE\s+CONS\.\s+TEÓRICO\s+UNIDAD\s+VALOR$/ui', $raw)) {
                    $headerSeen = true;

                    continue;
                }
                if (preg_match('/^Página\s+(\d+)$/ui', $raw, $match)) {
                    $footerSeen = ((int) $match[1]) === $number;

                    continue;
                }
                if (preg_match('/^(?:Total (.+?)\s+)?TOTAL CONSUMO\s*:\s*('.self::NUMBER.')\s*€$/u', $raw, $match)) {
                    $totalGroup = $match[1] ?? '';
                    $value = $this->decimal($match[2]);
                    $metadata['totals'][] = $source + ['name' => $totalGroup !== '' ? $totalGroup : $category, 'value' => $value, 'type' => $totalGroup !== '' ? 'group' : 'category'];
                    if ($totalGroup !== '') {
                        $this->checkTotal($value, $groupValues, $totalGroup, $number, $warnings);
                        $groupValues = $categoryValues = [];
                        $expectGroup = true;
                        $category = null;
                    } elseif ($expectGroup) {
                        // The document total comes after the last group total.
                        $this->checkTotal($value, $documentValues, 'informe', $number, $warnings);
                        $metadata['total_value'] = $value;
                        $metadata['totals'][array_key_last($metadata['totals'])]['type'] = 'document';
                    } else {
                        $this->checkTotal($value, $categoryValues, $category ?? 'categoría', $number, $warnings);
                        $categoryValues = [];
                    }

                    continue;
                }
                if (preg_match('/^% sobre ventas? netas?\b/ui', $raw)) {
                    $metadata['summary_lines'][] = $source;

                    continue;
                }
                if (preg_match('/^Ventas Netas \(sin Impuestos\):\s*('.self::NUMBER.')\s*€\s+Ventas Brutas \(sin Impuestos\):\s*('.self::NUMBER.')\s*€$/u', $raw, $match)) {
                    $metadata['net_sales'] = $this->decimal($match[1]);
                    $metadata['gross_sales'] = $this->decimal($match[2]);
                    $metadata['summary_lines'][] = $source;

                    continue;
                }

                // These are financial summary lines after the document total,
                // never products. Keep their values and provenance separately.
                if ($metadata['total_value'] !== null) {
                    if (preg_match('/^IMPORTE SERVICIOS\s*\(Transporte\s*\+\s*Manipulaci[oó]n\)\s*:\s*('.self::NUMBER.')\s*€$/ui', $raw, $match)) {
                        $metadata['services_amount'] = $this->decimal($match[1]);
                        $metadata['summary_lines'][] = $source;

                        continue;
                    }
                    if (preg_match('/^Ventas Netas\s*\+\s*Servicios\s*:\s*('.self::NUMBER.')\s*€\s+Ventas Brutas\s*\+\s*Servicios\s*:\s*('.self::NUMBER.')\s*€$/ui', $raw, $match)) {
                        $metadata['net_sales_with_services'] = $this->decimal($match[1]);
                        $metadata['gross_sales_with_services'] = $this->decimal($match[2]);
                        $metadata['summary_lines'][] = $source;

                        continue;
                    }
                    if (preg_match('/^Ventas (Netas|Brutas)\s*\+\s*Servicios\s*:\s*('.self::NUMBER.')\s*€$/ui', $raw, $match)) {
                        $key = mb_strtolower($match[1]) === 'netas' ? 'net_sales_with_services' : 'gross_sales_with_services';
                        $metadata[$key] = $this->decimal($match[2]);
                        $metadata['summary_lines'][] = $source;

                        continue;
                    }
                }

                $columns = $line['columns'] ?? null;
                if ($headerSeen && is_array($columns)
                    && preg_match('/^'.self::NUMBER.'$/u', $columns['quantity'] ?? '')
                    && preg_match('/^('.self::NUMBER.')\s*€$/u', $columns['value'] ?? '', $valueMatch)
                    && trim($columns['name'] ?? '') !== '' && trim($columns['unit'] ?? '') !== '') {
                    $name = trim($columns['name']);
                    $unit = trim($columns['unit']);
                    $identity = mb_strtolower(implode('|', [$group, $category, $name, $unit]), 'UTF-8');
                    $occurrence = ($occurrences[$identity] ?? 0) + 1;
                    $occurrences[$identity] = $occurrence;
                    $rows[] = $source + [
                        'id' => hash('sha256', $identity.'|'.$occurrence),
                        'name' => $name, 'quantity' => $this->decimal($columns['quantity']),
                        'quantity_raw' => $columns['quantity'], 'unit' => $unit,
                        'value' => $this->decimal($valueMatch[1]), 'value_raw' => $valueMatch[1],
                        'group' => $group, 'category' => $category,
                    ];
                    $categoryValues[] = $groupValues[] = $documentValues[] = $this->decimal($valueMatch[1]);
                    $rowCount++;

                    continue;
                }

                // Native report category headers are bold and indented less than products.
                // A malformed product, including one with no figures, must never become a category.
                if ($headerSeen && ($line['bold'] ?? false) && ($line['x0'] ?? 999) < 49
                    && ! preg_match('/[,€%]/u', $raw)) {
                    if ($expectGroup) {
                        $group = $raw;
                        $category = null;
                        $expectGroup = false;
                    } else {
                        $category = $raw;
                    }

                    continue;
                }
                $unparsed[] = $source;
            }
            if (! $headerSeen || ! $footerSeen) {
                $warnings[] = "La página {$number} no tiene cabecera o número de página reconocible.";
            }
            $pageRows[] = ['page' => $number, 'rows' => count($rows) - $countBefore];
        }

        if ($metadata['title'] === null || $metadata['period_start'] === null || $metadata['period_end'] === null) {
            $warnings[] = 'El documento no corresponde al informe de consumos teóricos esperado.';
        }
        if ($rowCount === 0) {
            $warnings[] = 'No se han encontrado productos en el informe.';
        }
        if ($metadata['total_value'] === null) {
            $warnings[] = 'Falta el total final del informe; puede estar incompleto.';
        }

        $coverage = [
            'pages' => count($pages), 'rows' => $rowCount, 'page_rows' => $pageRows,
            'warnings' => array_values(array_unique($warnings)), 'unparsed' => $unparsed,
            'complete' => $warnings === [] && $unparsed === [],
        ];
        if (! $coverage['complete']) {
            $coverage['message'] = $this->incompleteMessage($coverage);
        }

        return ['metadata' => $metadata, 'rows' => $rows, 'coverage' => $coverage];
    }

    private function incompleteMessage(array $coverage): string
    {
        $unchanged = "\nNo se ha importado ningún dato.";
        foreach ($coverage['warnings'] as $warning) {
            if (preg_match('/^La página (\d+) no contiene texto legible/u', $warning, $match)) {
                return "Página {$match[1]} sin texto legible.\nSi es una foto o un escaneo, usa el PDF original exportado desde el programa.".$unchanged;
            }
            if ($warning === 'La secuencia de páginas está incompleta.') {
                return "El informe tiene páginas incompletas o fuera de orden.\nVuelve a exportar el informe completo.".$unchanged;
            }
            if ($warning === 'El documento no corresponde al informe de consumos teóricos esperado.') {
                return "Tipo de informe no compatible.\nSelecciona el PDF del informe de consumos teóricos.".$unchanged;
            }
        }
        if ($coverage['unparsed'] !== []) {
            $line = $coverage['unparsed'][0];
            if (preg_match('/^(?:IMPORTE SERVICIOS|Ventas (?:Netas|Brutas))\b/ui', $line['raw'])) {
                return "Formato de resumen no reconocido · página {$line['page']}.\nEl PDF es legible, pero ese resumen no es compatible. Revisa el informe original o vuelve a exportarlo.".$unchanged;
            }

            return "Contenido no reconocido en la página {$line['page']}.\nRevisa esa página o vuelve a exportar el informe de consumos teóricos.".$unchanged;
        }

        return $coverage['warnings'][0]."\nRevisa el PDF original o vuelve a exportar el informe completo.".$unchanged;
    }

    private function decimal(string $value): string
    {
        return str_replace(',', '.', str_replace('.', '', $value));
    }

    /** Printed subtotals provide an independent check against missing table rows. */
    private function checkTotal(string $printed, array $values, string $name, int $page, array &$warnings): void
    {
        $total = $this->cents($printed);
        $sum = 0;
        foreach ($values as $value) {
            $cents = $this->cents($value);
            if ($cents === null || ($cents > 0 && $sum > PHP_INT_MAX - $cents)
                || ($cents < 0 && $sum < PHP_INT_MIN - $cents)) {
                $warnings[] = "El total de {$name} en la página {$page} no se ha podido comprobar con precisión.";

                return;
            }
            $sum += $cents;
        }
        // Each printed row and its subtotal may round independently to a cent.
        $roundingAllowance = intdiv(count($values) + 1, 2);
        if ($total === null || $values === [] || abs($sum - $total) > $roundingAllowance) {
            $warnings[] = "El total de {$name} en la página {$page} no coincide con sus filas; puede faltar información.";
        }
    }

    private function cents(string $value): ?int
    {
        if (! preg_match('/^([+-]?)(\d+)\.(\d{2})$/D', $value, $match)) {
            return null;
        }
        $digits = ltrim($match[2].$match[3], '0') ?: '0';
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return ($match[1] === '-' ? -1 : 1) * (int) $digits;
    }

    private function date(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);

        return $date && $date->format('d/m/Y') === $value ? $date->format('Y-m-d') : null;
    }
}
