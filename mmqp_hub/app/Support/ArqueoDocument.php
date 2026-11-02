<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;
use RuntimeException;

/** Separates reusable labels/bindings from the values entered for one date. */
class ArqueoDocument
{
    private function table(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><table><tbody id="sheet">'.$html.'</tbody></table>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return [$document, $document->getElementById('sheet')];
    }

    private function html(DOMDocument $document, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }

        return $html;
    }

    public function definition(string $html): string
    {
        [$document, $root] = $this->table($html);
        foreach ($root->getElementsByTagName('td') as $cell) {
            $content = $this->content($cell);
            $bound = $cell->hasAttribute('data-bound-emp') || $cell->hasAttribute('data-bound-date');
            if ($bound || $cell->getAttribute('data-arqueo-input') === 'true') {
                $content->textContent = '';
            }
            if ($bound) {
                $cell->removeAttribute('data-arqueo-input');
            } elseif (trim($content->textContent) === '') {
                $cell->setAttribute('data-arqueo-input', 'true');
            }
        }

        return $this->html($document, $root);
    }

    private function content(DOMElement $cell): DOMElement
    {
        foreach ($cell->getElementsByTagName('div') as $div) {
            if (in_array('cell-content', explode(' ', $div->getAttribute('class')), true)) {
                return $div;
            }
        }

        return $cell;
    }

    public function fill(string $html, array $values): string
    {
        [$document, $root] = $this->table($html);
        foreach ($root->getElementsByTagName('tr') as $r => $row) {
            $c = 0;
            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || ! in_array($cell->tagName, ['td', 'th'], true)) {
                    continue;
                }
                $key = $r.'-'.$c++;
                if ($cell->getAttribute('data-arqueo-input') === 'true' && ! $cell->hasAttribute('data-bound-emp') && ! $cell->hasAttribute('data-bound-date')) {
                    // Text nodes preserve line breaks without accepting user HTML.
                    $this->content($cell)->textContent = (string) ($values[$key] ?? '');
                }
            }
        }

        return $this->html($document, $root);
    }

    public function hash(string $html): string
    {
        return hash('sha256', $html);
    }

    public function read(string $path): ?array
    {
        if (! File::exists($path)) {
            return null;
        }
        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! is_string($data['html'] ?? null) || $data['html'] === '') {
            throw new RuntimeException('Invalid saved arqueo document.');
        }

        return $data;
    }

    public function save(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
