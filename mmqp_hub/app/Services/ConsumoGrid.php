<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;

/** Persists the spreadsheet layout while keeping links and quantities authoritative. */
class ConsumoGrid
{
    private const COLUMN_PROFILES = [
        'product,consumo,stock,pedido' => ['product', 'consumo', 'stock', 'pedido'],
        'product,consumo,stock' => ['product', 'consumo', 'stock'],
        'product,consumo,stock,pedido,m,v' => ['product', 'consumo', 'stock', 'pedido'],
        'quantity,product,consumo,stock,pedido' => ['product', 'consumo', 'stock', 'pedido'],
        'quantity,product,consumo,stock,pedido,m,v' => ['product', 'consumo', 'stock', 'pedido'],
    ];

    /** Validate all pages as one document so identities cannot disappear or repeat. */
    public function cleanPages(array $pages, array $suppliers, bool $withSources = false): array
    {
        $totalBytes = 0;
        foreach ($pages as $page) {
            $bytes = strlen($page['grid_html'] ?? '');
            if ($bytes > 1024 * 1024) {
                $this->invalid('El diseño de una hoja supera el tamaño máximo de 1 MB.');
            }
            $totalBytes += $bytes;
        }
        if ($totalBytes > 5 * 1024 * 1024) {
            $this->invalid('El documento supera el tamaño máximo de 5 MB.');
        }
        if (! collect($pages)->contains(fn ($page) => $page['grid_html'] !== null)) {
            return $pages; // Legacy fichas may contain data without a spreadsheet layout.
        }
        $separator = '<tr data-consumo-page-separator="'.bin2hex(random_bytes(16)).'"><td></td></tr>';
        $html = implode($separator, array_map(fn ($page) => $page['grid_html'] ?? '', $pages));
        $maximumBytes = 5 * 1024 * 1024 + strlen($separator) * count($pages);
        $clean = $withSources ? $this->withSources($html, $suppliers, $maximumBytes) : $this->clean($html, $suppliers, $maximumBytes);
        $grids = explode($separator, $clean);
        if (count($grids) !== count($pages)) {
            $this->invalid('No se han podido conservar todas las hojas del documento.');
        }
        foreach ($pages as $index => &$page) {
            if ($page['grid_html'] === null) {
                continue;
            }
            if (! preg_match('/<tr\b/i', $grids[$index]) || ! preg_match('/<(?:td|th)\b/i', $grids[$index])) {
                $this->invalid('Cada hoja del documento debe contener filas y celdas.');
            }
            $page['grid_html'] = $grids[$index];
        }
        unset($page);
        $this->productPageIds($pages);

        return $pages;
    }

    /** A repeated source is allowed only when explicitly bound on different pages. */
    public function productPageIds(array $pages): array
    {
        $providers = $products = $owners = [];
        foreach ($pages as $page) {
            if ($page['grid_html'] === null) {
                continue;
            }
            $document = new DOMDocument('1.0', 'UTF-8');
            $previousErrors = libxml_use_internal_errors(true);
            try {
                $document->loadHTML('<?xml encoding="UTF-8"><table>'.$page['grid_html'].'</table>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousErrors);
            }
            foreach ($document->getElementsByTagName('*') as $cell) {
                if ($cell->hasAttribute('data-consumo-provider')) {
                    $id = strtolower($cell->getAttribute('data-consumo-provider'));
                    if (isset($providers[$id]) && $providers[$id] !== $page['id']) {
                        $this->invalid('Cada proveedor debe pertenecer a una sola hoja.');
                    }
                    $providers[$id] = $page['id'];
                }
                if ($cell->hasAttribute('data-consumo-product')) {
                    $id = strtolower($cell->getAttribute('data-consumo-product'));
                    if (isset($products[$id]) && $products[$id] !== $page['id']) {
                        $this->invalid('Todas las celdas de un producto deben estar en la misma hoja.');
                    }
                    $products[$id] = $page['id'];
                    $owners[$id] = strtolower($cell->getAttribute('data-consumo-supplier'));
                }
            }
        }
        foreach ($products as $id => $pageId) {
            if (($providers[$owners[$id]] ?? null) !== $pageId) {
                $this->invalid('El producto y su proveedor deben estar en la misma hoja.');
            }
        }

        return $products;
    }

    /** Copy a template layout into a report, then derive all displayed values. */
    public function withSources(string $html, array $suppliers, int $maximumBytes = 1048576): string
    {
        if (strlen($html) > $maximumBytes) {
            $this->invalid('El diseño de la ficha supera el tamaño máximo de 1 MB.');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><table><tbody id="consumo-grid-root">'.$html.'</tbody></table>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $root = $document->getElementById('consumo-grid-root');
        if (! $root) {
            $this->invalid('El diseño no contiene una tabla válida.');
        }
        $products = [];
        foreach ($suppliers as $supplier) {
            foreach ($supplier['products'] as $product) {
                $products[strtolower($product['id'])] = $product;
            }
        }
        foreach ($root->getElementsByTagName('*') as $cell) {
            if ($cell->getAttribute('data-consumo-field') === 'consumo') {
                $product = $products[strtolower($cell->getAttribute('data-consumo-product'))] ?? null;
                if ($product) {
                    $cell->setAttribute('data-consumo-sources', json_encode($product['source_ids'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                }
            }
        }
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $this->clean($result, $suppliers, $maximumBytes);
    }

    public function clean(string $html, array $suppliers, int $maximumBytes = 1048576): string
    {
        if (strlen($html) > $maximumBytes) {
            $this->invalid('El diseño de la ficha supera el tamaño máximo de 1 MB.');
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><table><tbody id="consumo-grid-root">'.$html.'</tbody></table>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $root = $document->getElementById('consumo-grid-root');
        if (! $root) {
            $this->invalid('El diseño no contiene una tabla válida.');
        }

        $maximumRows = max(73, $root->getElementsByTagName('tr')->length);
        $this->cleanChildren($root, $maximumRows);
        if ($root->getElementsByTagName('tr')->length === 0
            || $root->getElementsByTagName('td')->length + $root->getElementsByTagName('th')->length === 0) {
            $this->invalid('El diseño debe contener filas y celdas.');
        }

        $providers = [];
        $products = [];
        $productProviders = [];
        foreach ($suppliers as $supplier) {
            $providers[strtolower($supplier['id'])] = $supplier;
            foreach ($supplier['products'] as $product) {
                $products[strtolower($product['id'])] = $product;
                $productProviders[strtolower($product['id'])] = strtolower($supplier['id']);
            }
        }
        $seenProviders = [];
        $providerFields = [];
        $seenProducts = [];
        foreach ($root->getElementsByTagName('*') as $cell) {
            $provider = $cell->getAttribute('data-consumo-provider');
            $product = $cell->getAttribute('data-consumo-product');
            $field = $cell->getAttribute('data-consumo-field');
            if (! $cell->hasAttribute('data-consumo-provider') && ! $cell->hasAttribute('data-consumo-product')
                && ! $cell->hasAttribute('data-consumo-field') && ! $cell->hasAttribute('data-consumo-supplier')
                && ! $cell->hasAttribute('data-consumo-sources') && ! $cell->hasAttribute('data-consumo-columns')) {
                continue;
            }
            if (! in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                $this->invalid('Los proveedores y productos deben estar vinculados a celdas de la ficha.');
            }
            if ($provider !== '') {
                $id = strtolower($provider);
                if ($product !== '' || $field !== '' || $cell->hasAttribute('data-consumo-supplier')
                    || $cell->hasAttribute('data-consumo-sources') || ! isset($providers[$id]) || isset($seenProviders[$id])) {
                    $this->invalid('Hay un proveedor repetido o que no pertenece a esta ficha.');
                }
                $seenProviders[$id] = true;
                $columns = $cell->hasAttribute('data-consumo-columns') ? $cell->getAttribute('data-consumo-columns') : 'product,consumo,stock,pedido';
                if (! isset(self::COLUMN_PROFILES[$columns])) {
                    $this->invalid('El proveedor tiene una distribución de columnas no válida.');
                }
                $providerFields[$id] = self::COLUMN_PROFILES[$columns];
                $cell->setAttribute('data-consumo-provider', $providers[$id]['id']);
                $this->setCellText($cell, $providers[$id]['name']);
            } elseif ($product !== '') {
                $id = strtolower($product);
                if (! isset($products[$id]) || ! in_array($field, ['product', 'consumo', 'stock', 'pedido'], true)
                    || $cell->hasAttribute('data-consumo-columns')
                    || isset($seenProducts[$id][$field])
                    || strtolower($cell->getAttribute('data-consumo-supplier')) !== $productProviders[$id]) {
                    $this->invalid('Las celdas del producto no coinciden con los datos de la ficha.');
                }
                $seenProducts[$id][$field] = true;
                $productData = $products[$id];
                $cell->setAttribute('data-consumo-product', $productData['id']);
                $cell->setAttribute('data-consumo-supplier', $providers[$productProviders[$id]]['id']);
                if ($field === 'consumo') {
                    try {
                        $sources = json_decode($cell->getAttribute('data-consumo-sources'), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $this->invalid('Los vínculos de una celda de consumo no son válidos.');
                    }
                    if ($sources !== $productData['source_ids']) {
                        $this->invalid('Los vínculos de la celda no coinciden con los consumos seleccionados.');
                    }
                    $cell->setAttribute('data-consumo-sources', json_encode($productData['source_ids'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                } elseif ($cell->hasAttribute('data-consumo-sources')) {
                    $this->invalid('Los vínculos solo pueden estar en la celda de Consumo.');
                }
                $text = match ($field) {
                    'product' => $productData['name'],
                    'consumo' => $this->consumptionText($productData['consumption'] ?? null),
                    default => $productData[$field] ?? '',
                };
                $this->setCellText($cell, $text, $field === 'consumo');
                if ($field === 'consumo') {
                    $cell->setAttribute('contenteditable', 'false');
                    foreach ($cell->getElementsByTagName('*') as $child) {
                        if ($child->hasAttribute('contenteditable')) {
                            $child->setAttribute('contenteditable', 'false');
                        }
                    }
                }
            } else {
                $this->invalid('Hay una celda de consumo sin producto.');
            }
        }
        if (count($seenProviders) !== count($providers)) {
            $this->invalid('Falta la celda de un proveedor en el diseño.');
        }
        foreach ($products as $id => $product) {
            $expected = $providerFields[$productProviders[$id]] ?? self::COLUMN_PROFILES['product,consumo,stock,pedido'];
            $actual = array_keys($seenProducts[$id] ?? []);
            if (count($actual) !== count($expected) || array_diff($expected, $actual) !== [] || array_diff($actual, $expected) !== []) {
                $this->invalid('Cada producto debe conservar todas las celdas de la distribución de su proveedor.');
            }
            foreach (['stock', 'pedido'] as $field) {
                if (! in_array($field, $expected, true) && trim((string) ($product[$field] ?? '')) !== '') {
                    $this->invalid('No se puede ocultar una columna que contiene datos del producto.');
                }
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private function cleanChildren(DOMNode $parent, int $maximumRows): void
    {
        $allowedTags = ['tr', 'td', 'th', 'div', 'span', 'b', 'strong', 'i', 'em', 'u', 'br', 'p', 'font', 's', 'strike', 'sub', 'sup'];
        $blockedTags = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'img', 'input', 'textarea', 'select', 'button', 'form', 'link', 'meta', 'base'];
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (in_array($tag, $blockedTags, true)) {
                    $parent->removeChild($node);

                    continue;
                }
                $this->cleanChildren($node, $maximumRows);
                if (! in_array($tag, $allowedTags, true)) {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);

                    continue;
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if ($name === 'style') {
                        $node->setAttribute('style', $this->cleanStyle($attribute->value));
                    } elseif (! in_array($name, ['class', 'rowspan', 'colspan', 'contenteditable', 'align', 'valign', 'color', 'face', 'size'], true)
                        && ! str_starts_with($name, 'data-')) {
                        $node->removeAttribute($attribute->name);
                    }
                }
                foreach (['rowspan' => $maximumRows, 'colspan' => 100] as $attribute => $maximum) {
                    if ($node->hasAttribute($attribute)) {
                        $value = filter_var($node->getAttribute($attribute), FILTER_VALIDATE_INT);
                        $node->setAttribute($attribute, (string) max(1, min($maximum, $value ?: 1)));
                    }
                }
            } elseif ($node->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($node);
            }
        }
    }

    private function cleanStyle(string $style): string
    {
        $allowed = '/^(?:background(?:-color)?|color|font(?:-size|-weight|-style|-family)?|text-(?:align|decoration)|vertical-align|line-height|border(?:-(?:top|right|bottom|left|color|width|style))?|(?:min-|max-)?(?:width|height)|padding(?:-(?:top|right|bottom|left))?|white-space|overflow(?:-wrap)?|word-break)$/';
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$property, $value] = array_map('trim', $parts);
            if (preg_match($allowed, strtolower($property)) && ! preg_match('/[\\\\<>@]|url\s*\(|expression\s*\(|\/\*/i', $value)) {
                $safe[] = $property.': '.$value;
            }
        }

        return implode('; ', $safe);
    }

    private function setCellText(DOMElement $cell, string $text, bool $force = false): void
    {
        // Keep the editor's inner content wrapper and its formatting when present.
        $content = $cell;
        foreach ($cell->childNodes as $child) {
            if ($child instanceof DOMElement && str_contains(' '.$child->getAttribute('class').' ', ' cell-content ')) {
                $content = $child;
                break;
            }
        }
        if ($content !== $cell) {
            foreach (iterator_to_array($cell->childNodes) as $child) {
                if ($child !== $content) {
                    $cell->removeChild($child);
                }
            }
        }
        if (! $force && $content->textContent === $text) {
            return;
        }
        while ($content->firstChild) {
            $content->removeChild($content->firstChild);
        }
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $index => $line) {
            if ($index > 0) {
                $content->appendChild($cell->ownerDocument->createElement('br'));
            }
            $content->appendChild($cell->ownerDocument->createTextNode($line));
        }
    }

    private function consumptionText(?array $consumption): string
    {
        if (! $consumption) {
            return '';
        }
        $amount = rtrim(rtrim($consumption['amount'], '0'), '.');

        return str_replace('.', ',', $amount).' '.$consumption['unit'];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['grid_html' => $message]);
    }
}
