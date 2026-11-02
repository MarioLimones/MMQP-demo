<?php

namespace App\Http\Controllers;

use App\Models\HorarioSemanal;
use App\Models\User;
use App\Support\ArqueoDocument;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class ArqueoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);

        // Use a real calendar date rather than accepting ambiguous Carbon input.
        $dateStr = $request->input('fecha', Carbon::now()->toDateString());
        $dateStr = $dateStr ?: Carbon::now()->toDateString();
        $date = Carbon::createFromFormat('!Y-m-d', $dateStr);
        $canDesign = (bool) $request->user()->es_admin;
        $workflowMode = $canDesign
            ? match ($request->query('modo')) { 'editar' => 'design', 'rellenar' => 'daily', default => 'entry' }
            : 'daily';

        $year = $date->isoWeekYear();
        $week = sprintf('%02d', $date->isoWeek());
        $semanaId = "{$year}-W{$week}";

        // Dias mapping para coincidir con la BD
        $diasSemanaMap = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ];
        $diaNombre = $diasSemanaMap[$date->dayOfWeekIso];

        $horario = HorarioSemanal::vigentes()->where('semana_id', $semanaId)->latest('id')->first();

        $empleadosDia = [];

        // Helper to remove accents for safe comparison
        $cleanString = function ($str) {
            $unwanted_array = ['Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
                'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
                'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
                'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y'];

            return strtolower(strtr(trim((string) $str), $unwanted_array));
        };

        $targetDayClean = $cleanString($diaNombre);

        if ($horario && isset($horario->resultados) && isset($horario->plantilla_diseno)) {
            // Find shifts for the day (ignoring case and accents)
            $turnosDelDia = collect($horario->plantilla_diseno)->first(function ($d) use ($targetDayClean, $cleanString) {
                return $cleanString($d['dia'] ?? '') === $targetDayClean;
            });

            if ($turnosDelDia && isset($turnosDelDia['turnos_requeridos'])) {
                // Map turno_id to su hora de inicio para saber si es mañana o noche
                $turnosInfo = [];
                foreach ($turnosDelDia['turnos_requeridos'] as $t) {
                    $turnosInfo[$t['turno_id']] = $t;
                }

                // Normalize resultados to a flat array of shifts
                $flatResultados = [];
                if (is_array($horario->resultados)) {
                    foreach ($horario->resultados as $r) {
                        if (isset($r['turnos_requeridos'])) {
                            // Format: nested by day
                            foreach ($r['turnos_requeridos'] as $t) {
                                if (isset($t['empleado_id']) && $t['empleado_id'] !== null) {
                                    $flatResultados[] = [
                                        'turno_id' => $t['turno_id'] ?? null,
                                        'empleado_id' => $t['empleado_id'],
                                    ];
                                }
                            }
                        } elseif (isset($r['empleado_id'])) {
                            // Format: flat array
                            $flatResultados[] = $r;
                        }
                    }
                }

                // Get employees assigned that day by matching turno_id with the day's shifts
                $resultadosDia = collect($flatResultados)->filter(function ($res) use ($turnosInfo) {
                    return isset($res['turno_id']) && isset($turnosInfo[$res['turno_id']]);
                });

                $users = collect($horario->empleados_snapshot)->keyBy('id');

                foreach ($resultadosDia as $res) {
                    if (! isset($res['empleado_id']) || ! isset($res['turno_id'])) {
                        continue;
                    }

                    $empId = $res['empleado_id'];
                    $turnoId = $res['turno_id'];

                    if (isset($users[$empId]) && isset($turnosInfo[$turnoId])) {
                        $user = $users[$empId];
                        $turno = $turnosInfo[$turnoId];

                        if (! isset($empleadosDia[$empId])) {
                            $empleadosDia[$empId] = [
                                'user' => (object) $user,
                                'manana' => null,
                                'noche' => null,
                                'cambios' => null,
                                'horarios_raw' => [],
                            ];
                        }

                        $horaInicio = Carbon::parse($turno['hora_inicio']);
                        // Si empieza antes de las 16:00, lo consideramos mañana, si no, noche
                        $tipoTurno = $horaInicio->hour < 16 ? 'manana' : 'noche';

                        $formatoTurno = substr($turno['hora_inicio'], 0, 5).'-'.substr($turno['hora_fin'], 0, 5);

                        if ($empleadosDia[$empId][$tipoTurno]) {
                            $empleadosDia[$empId][$tipoTurno] .= ', '.$formatoTurno;
                        } else {
                            $empleadosDia[$empId][$tipoTurno] = $formatoTurno;
                        }

                        $empleadosDia[$empId]['horarios_raw'][] = $formatoTurno;
                    }
                }
            }
        }

        // Load all active employees for the dropdown
        $empleadosList = User::employees()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'apellidos']);

        $templateData = null;
        $templateWarning = null;
        $dailySaved = false;
        $dailyAvailable = true;
        $documents = new ArqueoDocument;
        try {
            $saved = $workflowMode === 'daily' ? $documents->read(storage_path('app/arqueos/'.$dateStr.'.json')) : null;
            $base = $workflowMode === 'entry' ? null : ($saved ?: $documents->read(storage_path('app/arqueo_template.json')));
            if ($base) {
                $definition = $documents->definition($this->cleanTemplateHtml($base['html']));
                $templateData = ['html' => $saved ? $documents->fill($definition, $saved['values'] ?? []) : $definition];
                $dailySaved = $saved !== null;
            }
        } catch (Throwable $exception) {
            report($exception);
            $dailyAvailable = false;
            $templateWarning = 'No se ha podido recuperar la hoja guardada. Recarga la página antes de guardar para conservar los datos anteriores.';
        }
        $templateHash = isset($definition) ? $documents->hash($definition) : null;
        $entryPreviewHtml = null;
        if ($workflowMode === 'entry') {
            try {
                $previewSource = $documents->read(storage_path('app/arqueos/'.$dateStr.'.json'))
                    ?: $documents->read(storage_path('app/arqueo_template.json'));
                if ($previewSource) {
                    $previewDef = $documents->definition($this->cleanTemplateHtml($previewSource['html']));
                    $entryPreviewHtml = isset($previewSource['values']) && is_array($previewSource['values'])
                        ? $documents->fill($previewDef, $previewSource['values'])
                        : $previewDef;
                }
            } catch (Throwable) {
                $entryPreviewHtml = null;
            }
        }

        return view('admin.arqueo.index', compact('date', 'dateStr', 'empleadosList', 'empleadosDia', 'templateData', 'templateWarning', 'dailySaved', 'dailyAvailable', 'templateHash', 'canDesign', 'workflowMode', 'entryPreviewHtml'));
    }

    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'template' => ['required', 'array'],
            'template.html' => ['required', 'string', 'max:1000000'],
        ], [
            'template.required' => 'No se ha enviado el diseño.',
            'template.html.required' => 'El diseño está vacío.',
            'template.html.string' => 'El formato del diseño no es válido.',
            'template.html.max' => 'El diseño es demasiado grande para guardarlo.',
        ]);

        $documents = new ArqueoDocument;
        $template = ['html' => $documents->definition($this->cleanTemplateHtml($validated['template']['html']))];

        try {
            File::ensureDirectoryExists(storage_path('app'));
            // Readers always see a complete JSON document, including during a save.
            File::replace(storage_path('app/arqueo_template.json'), json_encode($template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'No se ha podido guardar el diseño. Conserva esta página abierta y vuelve a intentarlo.',
            ], 500);
        }

        return response()->json(['success' => true, 'templateHash' => $documents->hash($template['html'])]);
    }

    public function saveDaily(Request $request)
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'templateHash' => ['required', 'string', 'size:64'],
            'values' => ['present', 'array', 'max:40000'],
            'values.*' => ['nullable', 'string', 'max:10000'],
        ]);
        $documents = new ArqueoDocument;
        $path = storage_path('app/arqueos/'.$validated['fecha'].'.json');
        try {
            $existing = $documents->read($path);
            $template = $documents->read(storage_path('app/arqueo_template.json'));
            $definition = null;
            foreach ([$existing, $template] as $candidate) {
                if (! $candidate) {
                    continue;
                }
                $html = $documents->definition($this->cleanTemplateHtml($candidate['html']));
                if (hash_equals($documents->hash($html), $validated['templateHash'])) {
                    $definition = $html;
                    break;
                }
            }
            if ($definition === null) {
                return response()->json(['success' => false, 'message' => 'La plantilla ha cambiado. Conserva esta pestaña y recarga la hoja en otra pestaña antes de guardar.'], 409);
            }
            // Only values belonging to empty, editable cells can enter a daily record.
            $values = [];
            foreach ($validated['values'] as $key => $value) {
                if (preg_match('/^\d{1,3}-\d{1,3}$/', (string) $key) && $value !== null && $value !== '') {
                    $values[$key] = $value;
                }
            }
            $documents->save($path, [
                'html' => $definition,
                'values' => $values,
                'fecha' => $validated['fecha'],
                'updated_by' => $request->user()->id,
                'updated_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => 'No se ha podido guardar el arqueo. Tu trabajo sigue en esta pestaña; vuelve a intentarlo.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /** Keep the existing HTML template contract without executing pasted content. */
    private function cleanTemplateHtml(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><table><tbody id="arqueo-template-root">'.$html.'</tbody></table>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $root = $document->getElementById('arqueo-template-root');
        if (! $root) {
            throw ValidationException::withMessages(['template.html' => 'El diseño no contiene una tabla válida.']);
        }

        $allowedTags = ['tr', 'td', 'th', 'div', 'span', 'b', 'strong', 'i', 'em', 'u', 'br', 'p', 'font', 's', 'strike', 'sub', 'sup'];
        $blockedTags = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'img', 'input', 'textarea', 'select', 'button', 'form', 'link', 'meta', 'base'];
        $maximumRows = max(73, $root->getElementsByTagName('tr')->length);
        $clean = function (DOMNode $parent) use (&$clean, $allowedTags, $blockedTags, $maximumRows): void {
            foreach (iterator_to_array($parent->childNodes) as $node) {
                if ($node instanceof DOMElement) {
                    $tag = strtolower($node->tagName);
                    if (in_array($tag, $blockedTags, true)) {
                        $parent->removeChild($node);

                        continue;
                    }
                    $clean($node);
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
                            $node->setAttribute('style', $this->cleanTemplateStyle($attribute->value));
                        } elseif (! in_array($name, ['class', 'rowspan', 'colspan', 'contenteditable', 'align', 'valign', 'color', 'face', 'size'], true) && ! str_starts_with($name, 'data-')) {
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
        };
        $clean($root);

        if ($root->getElementsByTagName('tr')->length === 0 || $root->getElementsByTagName('td')->length === 0) {
            throw ValidationException::withMessages(['template.html' => 'El diseño debe contener filas y celdas.']);
        }

        $this->validateImportGeometry($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /** Imported geometry stays in HTML so save, reload and undo share one contract. */
    private function validateImportGeometry(DOMElement $root): void
    {
        $rows = iterator_to_array($root->getElementsByTagName('tr'));
        $first = $rows[0] ?? null;
        $metadataNames = ['data-arqueo-columns', 'data-arqueo-rows'];
        $hasGeometry = false;
        foreach ($root->getElementsByTagName('*') as $node) {
            foreach ($metadataNames as $name) {
                if (! $node->hasAttribute($name)) {
                    continue;
                }
                if ($node !== $first) {
                    throw ValidationException::withMessages(['template.html' => 'Las medidas de la plantilla deben estar en su primera fila.']);
                }
                $hasGeometry = true;
            }
        }
        if (! $hasGeometry) {
            return;
        }
        foreach ($metadataNames as $name) {
            if (! str_starts_with(ltrim($first->getAttribute($name)), '[')) {
                throw ValidationException::withMessages(['template.html' => 'Las medidas de la plantilla deben ser listas de números.']);
            }
        }
        try {
            $columns = json_decode($first->getAttribute($metadataNames[0]), true, 512, JSON_THROW_ON_ERROR);
            $heights = json_decode($first->getAttribute($metadataNames[1]), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['template.html' => 'Las medidas de la plantilla no son válidas.']);
        }
        $validSizes = function ($sizes, int $maximum, float $total): bool {
            return is_array($sizes) && array_is_list($sizes) && count($sizes) >= 1 && count($sizes) <= $maximum
                && ! array_filter($sizes, fn ($size) => (! is_int($size) && ! is_float($size)) || ! is_finite((float) $size) || $size <= 0)
                && abs(array_sum($sizes) - $total) <= 0.05;
        };
        if (! $validSizes($columns, 100, 100) || ! $validSizes($heights, 400, 276) || count($heights) !== count($rows)) {
            throw ValidationException::withMessages(['template.html' => 'Las medidas no coinciden con una hoja A4 válida.']);
        }
        $map = [];
        $width = 0;
        foreach ($rows as $r => $row) {
            $c = 0;
            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || ! in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    continue;
                }
                while (isset($map[$r][$c])) {
                    $c++;
                }
                $rowSpan = max(1, (int) $cell->getAttribute('rowspan'));
                $colSpan = max(1, (int) $cell->getAttribute('colspan'));
                if ($r + $rowSpan > count($heights) || $c + $colSpan > count($columns)) {
                    throw ValidationException::withMessages(['template.html' => 'Una casilla supera las medidas de la plantilla.']);
                }
                for ($rr = $r; $rr < $r + $rowSpan; $rr++) {
                    for ($cc = $c; $cc < $c + $colSpan; $cc++) {
                        if (isset($map[$rr][$cc])) {
                            throw ValidationException::withMessages(['template.html' => 'La plantilla contiene casillas superpuestas.']);
                        }
                        $map[$rr][$cc] = true;
                    }
                }
                $c += $colSpan;
                $width = max($width, $c);
            }
        }
        if ($width !== count($columns)) {
            throw ValidationException::withMessages(['template.html' => 'El número de columnas no coincide con las medidas de la plantilla.']);
        }
        foreach (array_keys($rows) as $r) {
            if (count($map[$r] ?? []) !== count($columns)) {
                throw ValidationException::withMessages(['template.html' => 'La plantilla debe conservar todas las casillas de cada fila.']);
            }
        }
        foreach ($metadataNames as $index => $name) {
            $first->setAttribute($name, json_encode($index === 0 ? $columns : $heights, JSON_THROW_ON_ERROR));
        }
    }

    private function cleanTemplateStyle(string $style): string
    {
        $allowed = '/^(?:background(?:-color)?|color|font(?:-size|-weight|-style|-family)?|text-(?:align|decoration)|vertical-align|line-height|border(?:-(?:top|right|bottom|left|color|width|style))?|(?:min-|max-)?(?:width|height)|padding(?:-(?:top|right|bottom|left))?|white-space|overflow(?:-wrap)?|word-break)$/';
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$property, $value] = array_map('trim', $parts);
            // Escapes and URL-bearing CSS cannot be produced by the editor controls.
            if (preg_match($allowed, strtolower($property)) && ! preg_match('/[\\\\<>@]|url\s*\(|expression\s*\(|\/\*/i', $value)) {
                $safe[] = $property.': '.$value;
            }
        }

        return implode('; ', $safe);
    }
}
