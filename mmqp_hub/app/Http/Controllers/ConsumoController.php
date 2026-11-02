<?php

namespace App\Http\Controllers;

use App\Models\ConsumoImport;
use App\Models\ConsumoDocument;
use App\Models\ConsumoProductLink;
use App\Models\ConsumoRecord;
use App\Models\ConsumoSheet;
use App\Services\ConsumoGrid;
use App\Services\ConsumoHistory;
use App\Services\ConsumoSheetSnapshot;
use App\Services\ConsumoPdfImporter;
use App\Services\ConsumoSourceIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ConsumoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['ficha' => ['nullable', 'integer', 'min:1']]);
        $history = app(ConsumoHistory::class)->calendar($request->only('month', 'day'));
        $sheet = $request->filled('ficha')
            ? ConsumoSheet::findOrFail($request->integer('ficha'))
            : ConsumoSheet::latest('updated_at')->latest('id')->first();

        return view('admin.consumos.index', ['history' => $history, 'initialData' => [
            'draft_namespace' => hash('sha256', config('database.default').'|'.config('database.connections.'.config('database.default').'.database').'|'.$request->user()->id),
            'sheets' => ConsumoSheet::latest('updated_at')->latest('id')->get(['id', 'title', 'updated_at']),
            'sheet' => $sheet ? $this->sheetData($sheet) : null,
            'document' => $this->currentDocumentData(),
            'imports' => ConsumoImport::latest('id')->get(['id', 'file_name', 'created_at', 'row_count']),
            'activeImport' => $sheet?->report ? $this->importData($sheet->report) : null,
            'upload_limit_bytes' => $this->uploadLimitBytes(),
        ]]);
    }

    public function history(Request $request)
    {
        return view('admin.consumos.history-calendar', [
            'history' => app(ConsumoHistory::class)->calendar($request->only('month', 'day')),
        ]);
    }

    public function showRecord(ConsumoRecord $record)
    {
        return view('admin.consumos.history-show', [
            'record' => $record,
            'snapshot' => app(ConsumoHistory::class)->viewSnapshot($record),
            'backUrl' => route('admin.consumos', ['month' => $record->registration_date->format('Y-m'), 'day' => $record->registration_date->toDateString()]).'#consumos-history-day',
        ]);
    }

    public function showSheet(ConsumoSheet $sheet)
    {
        return response()->json(['sheet' => $this->sheetData($sheet)]);
    }

    public function showImport(ConsumoImport $import)
    {
        return response()->json(['import' => $this->importData($import)]);
    }

    public function showDocument()
    {
        return response()->json(['document' => $this->currentDocumentData()]);
    }

    public function saveDocument(Request $request)
    {
        $data = $request->validate($this->documentRules());
        foreach ($data['suppliers'] as $si => $supplier) {
            foreach ($supplier['products'] as $pi => $product) {
                if ($product['source_ids'] !== []) {
                    throw ValidationException::withMessages(["suppliers.$si.products.$pi.source_ids" => 'Los consumos se vinculan al importar el PDF, después de guardar el documento.']);
                }
            }
        }
        $suppliers = $this->validatedSuppliers($data['suppliers'], null);
        $pages = app(ConsumoGrid::class)->cleanPages($this->inputPages($data), $this->suppliersWithConsumption($suppliers, null), true);

        return DB::transaction(function () use ($data, $suppliers, $pages, $request) {
            $document = ConsumoDocument::where('scope', 'default')->lockForUpdate()->first();
            if ($document && ((int) ($data['id'] ?? 0) !== $document->id || (int) ($data['version'] ?? 0) !== $document->version)) {
                return $this->staleDocumentResponse();
            }
            if (! $document && isset($data['id'])) {
                return $this->staleDocumentResponse();
            }
            if ($document && ! isset($data['pages']) && count($this->storedPages($document->payload)) > 1) {
                throw ValidationException::withMessages(['pages' => 'Guarda todas las hojas del documento para conservar su contenido.']);
            }
            $previousProducts = $document ? $this->documentProducts($document->payload['suppliers']) : [];
            $document ??= new ConsumoDocument;
            $attributes = ['scope' => 'default', 'title' => trim($data['title']), 'payload' => ['suppliers' => $suppliers, ...$this->pagePayload($pages)], 'version' => $document->exists ? $document->version + 1 : 1];
            if ($document->exists) {
                if (! $this->updateDocumentVersion($document, $attributes, (int) $data['version'])) {
                    return $this->staleDocumentResponse();
                }
            } else {
                $document->fill([...$attributes, 'created_by' => $request->user()->id])->save();
            }
            // The row UUID is the durable product identity. Removing or
            // repurposing it cannot leave a remembered link behind.
            $newProducts = $this->documentProducts($suppliers);
            foreach (ConsumoProductLink::where('document_id', $document->id)->get() as $link) {
                $before = $previousProducts[$link->product_id] ?? null;
                $after = $newProducts[$link->product_id] ?? null;
                if (! $before || ! $after || $before['name'] !== $after['name'] || $before['supplier_id'] !== $after['supplier_id']) {
                    $link->delete();
                }
            }

            return response()->json(['document' => $this->documentData($document)]);
        });
    }

    public function prepareImport(Request $request, ConsumoImport $import)
    {
        $data = $request->validate(['document_id' => ['required', 'integer', 'min:1'], 'document_version' => ['required', 'integer', 'min:1'],
            'review_all' => ['sometimes', 'boolean'], ...$this->importPageRules()]);
        $document = ConsumoDocument::findOrFail($data['document_id']);
        if ($document->version !== (int) $data['document_version']) {
            return $this->staleDocumentResponse();
        }
        $pages = $this->selectedDocumentPages($document, $data['page_ids'] ?? null);
        $prepared = $this->preparedDocument($document, $import, (bool) ($data['review_all'] ?? false), $pages);

        return response()->json(['document' => $this->documentData($document), 'import' => $this->importData($import),
            'page_ids' => array_column($pages, 'id'),
            'sheet' => $this->documentSnapshot($document, $import, $prepared['suppliers'], $pages),
            'pending' => $prepared['pending'], 'pending_product_ids' => array_column($prepared['pending'], 'product_id')]);
    }

    public function completeImport(Request $request, ConsumoImport $import)
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'], 'document_version' => ['required', 'integer', 'min:1'],
            'review_all' => ['sometimes', 'boolean'],
            ...$this->importPageRules(),
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'links' => ['present', 'array', 'list'], 'links.*' => ['required', 'array:product_id,source_ids,skip,product_name'],
            'links.*.product_id' => ['required', 'uuid'], 'links.*.source_ids' => ['present', 'array', 'list'],
            'links.*.source_ids.*' => ['required', 'string', 'max:100'], 'links.*.skip' => ['sometimes', 'boolean'],
            'links.*.product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($data, $import, $request) {
            $document = ConsumoDocument::lockForUpdate()->findOrFail($data['document_id']);
            $existing = ConsumoSheet::where('document_id', $document->id)->where('import_id', $import->id)
                ->where('document_version', $data['document_version'])->first();
            if ($existing) {
                // A retry may reuse this version only for the same selected
                // pages, even if the template has subsequently changed.
                $scope = $existing->payload['import_scope'] ?? [
                    'page_ids' => array_column($this->storedPages($existing->payload), 'id'),
                    'template_page_ids' => array_column($this->storedPages($existing->payload), 'id'),
                ];
                if ($this->canonicalPageIds($data['page_ids'] ?? $scope['template_page_ids']) !== $this->canonicalPageIds($scope['page_ids'])) {
                    return $this->staleDocumentResponse();
                }
                return response()->json(['sheet' => $this->sheetData($existing), 'document' => $this->documentData($document)]);
            }
            if ($document->version !== (int) $data['document_version']) {
                return $this->staleDocumentResponse();
            }
            $pages = $this->selectedDocumentPages($document, $data['page_ids'] ?? null);
            $prepared = $this->preparedDocument($document, $import, (bool) ($data['review_all'] ?? false), $pages);
            $products = $this->documentProducts($prepared['suppliers']);
            $rows = collect($import->data['rows'])->keyBy('id')->all();
            $manual = $skipped = [];
            foreach ($data['links'] as $index => $link) {
                $id = $link['product_id'];
                if (! isset($products[$id]) || isset($manual[$id])) {
                    throw ValidationException::withMessages(["links.$index.product_id" => 'Selecciona una sola vez un producto de este documento.']);
                }
                if (($link['skip'] ?? false) && $link['source_ids'] !== []) {
                    throw ValidationException::withMessages(["links.$index.source_ids" => 'Elige un consumo o indica que no aparece en el PDF.']);
                }
                if (! ($link['skip'] ?? false) && $link['source_ids'] === []) {
                    throw ValidationException::withMessages(["links.$index.source_ids" => 'Selecciona un consumo o indica que no aparece en el PDF.']);
                }
                foreach ($link['source_ids'] as $sourceId) {
                    if (! isset($rows[$sourceId])) {
                        throw ValidationException::withMessages(["links.$index.source_ids" => 'Este consumo no pertenece al PDF importado.']);
                    }
                }
                $manual[$id] = $link;
                if ($link['skip'] ?? false) {
                    $skipped[$id] = true;
                }
            }
            $templateSuppliers = $document->payload['suppliers'];
            $templatePositions = [];
            foreach ($templateSuppliers as $si => $supplier) {
                foreach ($supplier['products'] as $pi => $product) {
                    $templatePositions[$product['id']] = [$si, $pi];
                }
            }
            foreach ($prepared['suppliers'] as $si => &$supplier) {
                foreach ($supplier['products'] as $pi => &$product) {
                    $link = $manual[$product['id']] ?? null;
                    if ($link) {
                        $product['source_ids'] = $link['source_ids'];
                        if ($product['name'] === '' && $link['source_ids'] !== []) {
                            $product['name'] = trim((string) ($link['product_name'] ?? $rows[$link['source_ids'][0]]['name']));
                            if ($product['name'] === '') {
                                throw ValidationException::withMessages(['links' => 'Escribe el nombre del producto nuevo.']);
                            }
                            [$templateSi, $templatePi] = $templatePositions[$product['id']];
                            $templateSuppliers[$templateSi]['products'][$templatePi]['name'] = $product['name'];
                        }
                    }
                    if ($product['source_ids'] === [] && ! isset($skipped[$product['id']])) {
                        throw ValidationException::withMessages(['links' => 'Quedan productos sin resolver. Vincúlalos o indica que no aparecen en este PDF.']);
                    }
                }
                unset($product);
            }
            unset($supplier);
            $suppliers = $this->validatedSuppliers($prepared['suppliers'], $import, app(ConsumoGrid::class)->productPageIds($pages));
            $snapshot = $this->documentSnapshot($document, $import, $suppliers, $pages);
            $importScope = ['page_ids' => array_column($pages, 'id'), 'template_page_ids' => array_column($this->storedPages($document->payload), 'id')];
            $templatePages = app(ConsumoGrid::class)->cleanPages($this->storedPages($document->payload), $this->suppliersWithConsumption($templateSuppliers, null), true);
            if (! $this->updateDocumentVersion($document, ['payload' => ['suppliers' => $templateSuppliers, ...$this->pagePayload($templatePages)], 'version' => $document->version + 1], (int) $data['document_version'])) {
                return $this->staleDocumentResponse();
            }
            $identity = app(ConsumoSourceIdentity::class);
            $sourceIndex = $identity->index($import);
            foreach ($manual as $productId => $link) {
                if (isset($skipped[$productId])) {
                    if ($data['review_all'] ?? false) {
                        // A full manual review makes a blank an explicit new
                        // mapping decision; it must not revive an older source.
                        ConsumoProductLink::where('document_id', $document->id)->where('product_id', $productId)->delete();
                    }
                    // A source absent from a normal new report can still return
                    // in a later report without losing its remembered identity.
                    continue;
                }
                $sources = array_map(function ($id) use ($rows, $import, $identity, $sourceIndex) {
                    $source = $identity->identity($rows[$id], $import);
                    $source['ambiguous'] = ($sourceIndex['counts'][$identity->contextKey($source)] ?? 0) > 1;

                    return $source;
                }, $link['source_ids']);
                ConsumoProductLink::updateOrCreate(['document_id' => $document->id, 'product_id' => $productId], ['sources' => $sources]);
            }
            $sheet = ConsumoSheet::create(['title' => isset($data['title']) ? trim($data['title']) : $document->title,
                'document_id' => $document->id, 'document_version' => (int) $data['document_version'], 'import_id' => $import->id,
                'payload' => ['suppliers' => $suppliers, ...$this->pagePayload($snapshot['pages']), 'skipped_product_ids' => array_keys($skipped), 'import_scope' => $importScope],
                'version' => 1, 'created_by' => $request->user()->id]);

            app(ConsumoHistory::class)->record($sheet, $request->user());

            return response()->json(['sheet' => $this->sheetData($sheet), 'document' => $this->documentData($document)], 201);
        });
    }

    public function saveSheet(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'version' => ['required_with:id', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'grid_html' => ['nullable', 'string', 'max:1048576'],
            ...$this->pageRules(),
            'import_id' => ['present', 'nullable', 'integer', 'exists:consumo_imports,id'],
            'suppliers' => ['present', 'array', 'list'],
            'suppliers.*' => ['required', 'array:id,name,products'],
            'suppliers.*.id' => ['required', 'uuid'],
            'suppliers.*.name' => ['required', 'string', 'max:255'],
            'suppliers.*.products' => ['present', 'array', 'list'],
            'suppliers.*.products.*' => ['required', 'array:id,name,stock,pedido,source_ids'],
            'suppliers.*.products.*.id' => ['required', 'uuid'],
            'suppliers.*.products.*.name' => ['present', 'nullable', 'string', 'max:255'],
            'suppliers.*.products.*.stock' => ['present', 'nullable', 'string', 'max:500'],
            'suppliers.*.products.*.pedido' => ['present', 'nullable', 'string', 'max:500'],
            'suppliers.*.products.*.source_ids' => ['present', 'array', 'list'],
            'suppliers.*.products.*.source_ids.*' => ['required', 'string', 'max:100'],
        ]);

        foreach (['id', 'version', 'import_id'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) $data[$key];
            }
        }

        $import = isset($data['import_id']) ? ConsumoImport::findOrFail($data['import_id']) : null;
        $pages = $this->inputPages($data);
        $suppliers = $this->validatedSuppliers($data['suppliers'], $import, app(ConsumoGrid::class)->productPageIds($pages));
        $pages = app(ConsumoGrid::class)->cleanPages($pages, $this->suppliersWithConsumption($suppliers, $import));

        return DB::transaction(function () use ($data, $suppliers, $pages, $request) {
            $sheet = isset($data['id']) ? ConsumoSheet::lockForUpdate()->findOrFail($data['id']) : new ConsumoSheet;
            if ($sheet->exists && $sheet->version !== $data['version']) {
                return $this->staleResponse();
            }
            if ($sheet->exists && ! isset($data['pages']) && count($this->storedPages($sheet->payload)) > 1) {
                throw ValidationException::withMessages(['pages' => 'Guarda todas las hojas de la ficha para conservar su contenido.']);
            }

            $attributes = [
                'title' => trim($data['title']),
                'import_id' => $data['import_id'] ?? null,
                'payload' => ['suppliers' => $suppliers, ...$this->pagePayload($pages),
                    ...(isset($sheet->payload['import_scope']) ? ['import_scope' => $sheet->payload['import_scope']] : [])],
                'version' => $sheet->exists ? $sheet->version + 1 : 1,
            ];
            if ($sheet->exists) {
                // This compare-and-swap also protects drivers where lockForUpdate is unavailable.
                $updated = ConsumoSheet::whereKey($sheet->id)->where('version', $data['version'])->update([
                    ...$attributes,
                    'payload' => json_encode($attributes['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                if ($updated !== 1) {
                    return $this->staleResponse();
                }
                $sheet->refresh();
            } else {
                $sheet->fill([...$attributes, 'created_by' => $request->user()->id])->save();
            }

            app(ConsumoHistory::class)->record($sheet, $request->user());

            return response()->json(['sheet' => $this->sheetData($sheet)]);
        });
    }

    public function importPdf(Request $request, ConsumoPdfImporter $importer)
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:20480']], [
            'pdf.required' => 'Selecciona el PDF de consumos que quieres importar.',
            'pdf.file' => 'No se ha podido leer el archivo adjunto. Vuelve a seleccionarlo.',
            'pdf.mimes' => 'El archivo debe ser un PDF. Selecciona el informe de consumos teóricos.',
            'pdf.max' => 'El PDF supera el límite de 20 MB. Exporta un informe más pequeño.',
            'pdf.uploaded' => 'No se ha podido subir el PDF. Comprueba su tamaño y vuelve a intentarlo.',
        ]);
        $file = $request->file('pdf');
        try {
            $data = $importer->import($file->getRealPath());
            $this->validateImportCoverage($data);
            // A reimport is a new report even when the original PDF is identical.
            // Keep the parser identity for traceability while every link belongs
            // exclusively to this immutable import, never to a matching name.
            $scope = str_replace('-', '', (string) Str::uuid());
            foreach ($data['rows'] as &$row) {
                $row['original_source_id'] = $row['id'];
                $row['id'] = $scope.':'.$row['id'];
            }
            unset($row);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['pdf' => $exception->getMessage()]);
        }

        $disk = Storage::disk('local');
        $path = null;
        try {
            $path = $file->store('consumos/originales', 'local');
            if (! $path) {
                throw new RuntimeException('No se ha podido conservar el PDF original.');
            }
            $import = ConsumoImport::create([
                'file_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'original_path' => $path,
                'data' => $data,
                'row_count' => count($data['rows']),
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            if ($path) {
                $disk->delete($path);
            }
            report($exception);

            return response()->json(['message' => 'No se ha podido guardar la importación completa. Vuelve a intentarlo; la ficha conserva sus datos.'], 500);
        }

        // Importing never updates a sheet or guesses which product a row belongs to.
        return response()->json(['import' => $this->importData($import)], 201);
    }

    private function validateImportCoverage(array $data): void
    {
        $rows = $data['rows'] ?? null;
        $coverage = $data['coverage'] ?? [];
        if (! is_array($rows) || ! array_is_list($rows) || $rows === [] || ($coverage['complete'] ?? false) !== true
            || ($coverage['rows'] ?? null) !== count($rows) || ! empty($coverage['unparsed'])) {
            throw new RuntimeException('El PDF no se ha podido leer por completo. No se ha importado ningún dato; revisa el informe.');
        }
        $ids = [];
        foreach ($rows as $row) {
            if (! is_string($row['id'] ?? null) || isset($ids[$row['id']]) || ! is_string($row['name'] ?? null)
                || ! is_string($row['unit'] ?? null) || ! is_string($row['quantity'] ?? null)
                || ! preg_match('/^[+-]?\d+\.\d+$/D', $row['quantity'])) {
                throw new RuntimeException('El PDF contiene un consumo que no se ha podido conservar con precisión. No se ha importado ningún dato.');
            }
            $ids[$row['id']] = true;
        }
    }

    private function documentRules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'min:1'], 'version' => ['nullable', 'required_with:id', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'], 'grid_html' => ['nullable', 'string', 'max:1048576'],
            ...$this->pageRules(),
            'suppliers' => ['present', 'array', 'list'], 'suppliers.*' => ['required', 'array:id,name,products'],
            'suppliers.*.id' => ['required', 'uuid'], 'suppliers.*.name' => ['required', 'string', 'max:255'],
            'suppliers.*.products' => ['present', 'array', 'list'],
            'suppliers.*.products.*' => ['required', 'array:id,name,stock,pedido,source_ids'],
            'suppliers.*.products.*.id' => ['required', 'uuid'], 'suppliers.*.products.*.name' => ['present', 'nullable', 'string', 'max:255'],
            'suppliers.*.products.*.stock' => ['present', 'nullable', 'string', 'max:500'],
            'suppliers.*.products.*.pedido' => ['present', 'nullable', 'string', 'max:500'],
            'suppliers.*.products.*.source_ids' => ['present', 'array', 'list'],
            'suppliers.*.products.*.source_ids.*' => ['required', 'string', 'max:100'],
        ];
    }

    private function currentDocumentData(): ?array
    {
        $document = ConsumoDocument::where('scope', 'default')->first();
        if ($document) {
            return $this->documentData($document);
        }
        // Existing installations keep their approved layout. It becomes a
        // reusable document only when the user saves it, without copying links.
        $legacy = ConsumoSheet::latest('updated_at')->latest('id')->first();
        if (! $legacy) {
            return null;
        }
        $suppliers = $legacy->payload['suppliers'] ?? [];
        foreach ($suppliers as &$supplier) {
            foreach ($supplier['products'] as &$product) {
                $product['source_ids'] = [];
            }
            unset($product);
        }
        unset($supplier);
        $suppliers = $this->suppliersWithConsumption($suppliers, null);

        $pages = app(ConsumoGrid::class)->cleanPages($this->storedPages($legacy->payload), $suppliers, true);

        return ['id' => null, 'version' => null, 'title' => $legacy->title, 'suppliers' => $suppliers, ...$this->pagePayload($pages)];
    }

    private function documentData(ConsumoDocument $document): array
    {
        return ['id' => $document->id, 'version' => $document->version, 'title' => $document->title,
            'suppliers' => $this->suppliersWithConsumption($document->payload['suppliers'] ?? [], null),
            ...$this->pagePayload($this->storedPages($document->payload)), 'updated_at' => $document->updated_at->toISOString()];
    }

    private function documentProducts(array $suppliers): array
    {
        $products = [];
        foreach ($suppliers as $supplier) {
            foreach ($supplier['products'] as $product) {
                $products[$product['id']] = [...$product, 'supplier_id' => $supplier['id']];
            }
        }

        return $products;
    }

    private function preparedDocument(ConsumoDocument $document, ConsumoImport $import, bool $reviewAll, array $pages): array
    {
        $identity = app(ConsumoSourceIdentity::class);
        $index = $identity->index($import);
        $links = ConsumoProductLink::where('document_id', $document->id)->get()->keyBy('product_id');
        $suppliers = $this->documentSuppliersForPages($document, $pages);
        $productPages = app(ConsumoGrid::class)->productPageIds($pages);
        $pending = [];
        foreach ($suppliers as &$supplier) {
            foreach ($supplier['products'] as $position => &$product) {
                $link = $links->get($product['id']);
                $resolved = $reviewAll ? ['ids' => [], 'reason' => 'manual_review']
                    : ($link ? $identity->resolve($link->sources, $index) : ['ids' => [], 'reason' => 'new_product']);
                $product['source_ids'] = $resolved['ids'];
                if ($resolved['reason']) {
                    $pending[] = ['product_id' => $product['id'], 'product_name' => $product['name'], 'supplier_id' => $supplier['id'],
                        'supplier_name' => $supplier['name'], 'page_id' => $productPages[strtolower($product['id'])] ?? null,
                        'position' => $position + 1, 'reason' => $resolved['reason']];
                }
            }
            unset($product);
        }
        unset($supplier);

        return ['suppliers' => $suppliers, 'pending' => $pending];
    }

    private function documentSnapshot(ConsumoDocument $document, ConsumoImport $import, array $suppliers, array $pages): array
    {
        $calculated = $this->suppliersWithConsumption($suppliers, $import);
        $pages = app(ConsumoGrid::class)->cleanPages($pages, $calculated, true);

        return ['id' => null, 'version' => null, 'title' => $document->title, 'import_id' => $import->id, 'suppliers' => $calculated,
            'page_ids' => array_column($pages, 'id'), ...$this->pagePayload($pages)];
    }

    private function importPageRules(): array
    {
        return ['page_ids' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:20'],
            'page_ids.*' => ['required', 'uuid', 'distinct:ignore_case']];
    }

    private function canonicalPageIds(array $pageIds): array
    {
        $ids = array_map('strtolower', $pageIds);
        sort($ids, SORT_STRING);

        return $ids;
    }

    private function selectedDocumentPages(ConsumoDocument $document, ?array $pageIds): array
    {
        $pages = $this->storedPages($document->payload);
        if ($pageIds === null) {
            return $pages; // Older clients import the complete saved template.
        }
        $selected = array_fill_keys(array_map('strtolower', $pageIds), true);
        $known = array_fill_keys(array_map('strtolower', array_column($pages, 'id')), true);
        if (array_diff_key($selected, $known) !== []) {
            throw ValidationException::withMessages(['page_ids' => 'Alguna hoja seleccionada ya no pertenece a la plantilla guardada. Recarga las hojas antes de importar.']);
        }

        return array_values(array_filter($pages, fn ($page) => isset($selected[strtolower($page['id'])])));
    }

    private function documentSuppliersForPages(ConsumoDocument $document, array $pages): array
    {
        $suppliers = $document->payload['suppliers'];
        if (count($pages) === count($this->storedPages($document->payload))) {
            return $suppliers; // Preserve data-only legacy documents as well.
        }
        $products = app(ConsumoGrid::class)->productPageIds($pages);
        $providers = [];
        // Empty supplier tables still belong to their selected page, so retain
        // their heading even when there are no product cells to find them by.
        foreach ($pages as $page) {
            if ($page['grid_html'] === null) {
                continue;
            }
            $html = new \DOMDocument('1.0', 'UTF-8');
            $previousErrors = libxml_use_internal_errors(true);
            try {
                $html->loadHTML('<?xml encoding="UTF-8"><table>'.$page['grid_html'].'</table>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousErrors);
            }
            foreach ($html->getElementsByTagName('*') as $cell) {
                if ($cell->hasAttribute('data-consumo-provider')) {
                    $providers[strtolower($cell->getAttribute('data-consumo-provider'))] = true;
                }
            }
        }
        $selected = [];
        foreach ($suppliers as $supplier) {
            $supplier['products'] = array_values(array_filter($supplier['products'], fn ($product) => isset($products[strtolower($product['id'])])));
            if ($supplier['products'] !== [] || isset($providers[strtolower($supplier['id'])])) {
                $selected[] = $supplier;
            }
        }

        return $selected;
    }

    private function updateDocumentVersion(ConsumoDocument $document, array $attributes, int $version): bool
    {
        if (isset($attributes['payload'])) {
            $attributes['payload'] = json_encode($attributes['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $updated = ConsumoDocument::whereKey($document->id)->where('version', $version)->update([...$attributes, 'updated_at' => now()]);
        if ($updated !== 1) {
            return false;
        }
        $document->refresh();

        return true;
    }

    private function staleDocumentResponse()
    {
        return response()->json(['message' => 'El documento o sus vínculos han cambiado en otra sesión. Recarga el documento antes de continuar.'], 409);
    }

    private function validatedSuppliers(array $suppliers, ?ConsumoImport $import, array $productPages = []): array
    {
        $rows = collect($import?->data['rows'] ?? [])->keyBy('id')->all();
        $identifiers = [];
        $assignedSources = [];
        $result = [];
        foreach ($suppliers as $si => $supplier) {
            $this->uniqueIdentifier($supplier['id'], "suppliers.$si.id", $identifiers);
            $products = [];
            foreach ($supplier['products'] as $pi => $product) {
                $field = "suppliers.$si.products.$pi";
                $this->uniqueIdentifier($product['id'], "$field.id", $identifiers);
                $name = trim((string) ($product['name'] ?? ''));
                // Empty typed rows belong to the reusable template. Once a row
                // contains operational data, its product must be identifiable.
                if ($name === '' && ($product['source_ids'] !== []
                    || trim((string) ($product['stock'] ?? '')) !== ''
                    || trim((string) ($product['pedido'] ?? '')) !== '')) {
                    throw ValidationException::withMessages(["$field.name" => 'Escribe el nombre del producto antes de vincular su consumo o rellenar Stock y Pedido.']);
                }
                $unit = null;
                $pageId = $productPages[strtolower($product['id'])] ?? 'legacy';
                foreach ($product['source_ids'] as $id) {
                    if (! isset($rows[$id])) {
                        throw ValidationException::withMessages(["$field.source_ids" => 'Este consumo no pertenece al informe seleccionado. Selecciona una fila del PDF importado.']);
                    }
                    if (isset($assignedSources[$pageId][$id])) {
                        throw ValidationException::withMessages(["$field.source_ids" => 'Este consumo ya está vinculado en esta hoja. Cada fila del informe solo puede asignarse una vez por hoja.']);
                    }
                    if ($unit !== null && $unit !== $rows[$id]['unit']) {
                        throw ValidationException::withMessages(["$field.source_ids" => 'No se pueden sumar consumos con distintas unidades. Crea productos separados para conservarlos.']);
                    }
                    $assignedSources[$pageId][$id] = true;
                    $unit = $rows[$id]['unit'];
                }
                $products[] = [
                    'id' => $product['id'],
                    'name' => $name,
                    'stock' => $product['stock'] ?? '',
                    'pedido' => $product['pedido'] ?? '',
                    'source_ids' => $product['source_ids'],
                ];
            }
            $result[] = ['id' => $supplier['id'], 'name' => trim($supplier['name']), 'products' => $products];
        }

        return $result;
    }

    private function uniqueIdentifier(string $id, string $field, array &$seen): void
    {
        $key = strtolower($id);
        if (isset($seen[$key])) {
            throw ValidationException::withMessages([$field => 'Cada proveedor y producto debe tener un identificador único.']);
        }
        $seen[$key] = true;
    }

    private function sheetData(ConsumoSheet $sheet): array
    {
        return app(ConsumoSheetSnapshot::class)->make($sheet);
    }

    private function pageRules(): array
    {
        return [
            'pages' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:20'],
            'pages.*' => ['required', 'array:id,title,grid_html'],
            'pages.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'pages.*.title' => ['present', 'string', 'max:255'],
            'pages.*.grid_html' => ['present', 'nullable', 'string', 'max:1048576'],
        ];
    }

    private function inputPages(array $data): array
    {
        $pages = $data['pages'] ?? $this->storedPages($data);
        // Older clients edit the first-page alias after reading the response.
        if (array_key_exists('grid_html', $data)) {
            $pages[0]['grid_html'] = $data['grid_html'];
        }

        return $pages;
    }

    private function storedPages(array $payload): array
    {
        return $payload['pages'] ?? [['id' => '00000000-0000-4000-8000-000000000001', 'title' => 'Hoja 1', 'grid_html' => $payload['grid_html'] ?? null]];
    }

    private function pagePayload(array $pages): array
    {
        return ['pages' => $pages, 'grid_html' => $pages[0]['grid_html'] ?? null];
    }

    private function suppliersWithConsumption(array $suppliers, ?ConsumoImport $import): array
    {
        return app(ConsumoSheetSnapshot::class)->suppliersWithConsumption($suppliers, $import);
    }

    private function importData(ConsumoImport $import): array
    {
        return ['id' => $import->id, 'file_name' => $import->file_name, 'created_at' => $import->created_at->toISOString(), 'row_count' => $import->row_count, ...$import->data];
    }

    private function staleResponse()
    {
        return response()->json(['message' => 'La ficha ha cambiado en otra sesión. Recárgala antes de guardar para conservar esos cambios.'], 409);
    }

    private function uploadLimitBytes(): int
    {
        $limits = [20 * 1024 * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $bytes = $this->iniBytes((string) ini_get($setting));
            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return min($limits);
    }

    /** PHP shorthand bytes; nonpositive values do not constrain uploads. */
    private function iniBytes(string $value): int
    {
        if (! preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*([KMG]?)\s*$/iD', $value, $parts)) {
            return 0;
        }
        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3][strtoupper($parts[2])];
        $bytes = (float) $parts[1] * (1024 ** $power);

        if ($bytes >= PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return $bytes > 0 ? (int) floor($bytes) : 0;
    }
}
