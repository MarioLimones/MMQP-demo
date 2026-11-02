<?php

namespace App\Services;

use App\Models\ConsumoRecord;
use App\Models\ConsumoSheet;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConsumoHistory
{
    public const TIMEZONE = 'Europe/Madrid';

    public function __construct(private ConsumoSheetSnapshot $snapshots) {}

    /** Called in the sheet's save transaction; retries keep the first snapshot. */
    public function record(ConsumoSheet $sheet, ?User $actor, ?DateTimeInterface $registeredAt = null): ConsumoRecord
    {
        // Read the identity from the account, never from a submitted author name.
        $author = $actor?->getKey() ? User::find($actor->getKey()) : null;
        if (! $author || trim($author->nombre.' '.$author->apellidos) === '') {
            throw ValidationException::withMessages([
                'author' => 'No se puede guardar el consumo sin un autor identificado. Revisa el nombre de tu cuenta e inicia sesión de nuevo.',
            ]);
        }

        return $this->archive($sheet, $author, $registeredAt);
    }

    /** Legacy imports can retain an explicitly unknown author; new saves cannot. */
    private function archive(ConsumoSheet $sheet, ?User $actor, ?DateTimeInterface $registeredAt = null): ConsumoRecord
    {
        $at = $registeredAt ? CarbonImmutable::instance($registeredAt)->utc() : CarbonImmutable::now('UTC');
        $sheet->loadMissing('report');
        $metadata = $sheet->report?->data['metadata'] ?? [];
        $name = $actor ? trim($actor->nombre.' '.$actor->apellidos) : '';

        return ConsumoRecord::firstOrCreate(
            ['sheet_id' => $sheet->id, 'sheet_version' => $sheet->version],
            [
                'registered_at' => $at,
                'registration_date' => $at->setTimezone(self::TIMEZONE)->toDateString(),
                'actor_id' => $actor?->id,
                'actor_name' => $name !== '' ? $name : 'Autor no registrado',
                'title' => $sheet->title,
                'import_metadata' => [...$metadata, 'file_name' => $sheet->report?->file_name],
                'snapshot' => $this->snapshots->make($sheet),
            ],
        );
    }

    /** Only the current state of an older sheet can be recovered. */
    public function backfill(ConsumoSheet $sheet): ConsumoRecord
    {
        $firstVersion = $sheet->version === 1;
        $actor = $firstVersion && $sheet->created_by ? User::find($sheet->created_by) : null;

        $savedAt = CarbonImmutable::parse($firstVersion ? $sheet->created_at : $sheet->updated_at, 'UTC');

        return $this->archive($sheet, $actor, $savedAt);
    }

    public function calendar(array $query = []): array
    {
        $data = Validator::make($query, [
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/'],
            'day' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month'] ?? $today->format('Y-m'), self::TIMEZONE);
        if (! empty($data['day']) && ! str_starts_with($data['day'], $month->format('Y-m').'-')) {
            throw ValidationException::withMessages(['day' => 'Elige un día del mes que estás consultando.']);
        }
        $records = ConsumoRecord::whereBetween('registration_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderByDesc('registered_at')->orderByDesc('id')
            ->get(['id', 'sheet_id', 'sheet_version', 'title', 'actor_name', 'registered_at', 'registration_date', 'import_metadata'])
            ->map(fn (ConsumoRecord $record) => $this->summary($record));
        $byDay = $records->groupBy('date');
        $selectedDay = $data['day'] ?? ($today->format('Y-m') === $month->format('Y-m')
            ? $today->toDateString() : ($byDay->keys()->sort()->first() ?? $month->toDateString()));
        $selected = CarbonImmutable::createFromFormat('!Y-m-d', $selectedDay, self::TIMEZONE);
        $start = $month->startOfWeek(1);
        $dayCount = 42;
        $days = [];
        for ($index = 0; $index < $dayCount; $index++) {
            $date = $start->addDays($index);
            $key = $date->toDateString();
            $days[] = [
                'date' => $key, 'day' => $date->day,
                'in_month' => $date->format('Y-m') === $month->format('Y-m'),
                'is_today' => $key === $today->toDateString(), 'is_future' => $date->greaterThan($today),
                'selected' => $key === $selectedDay,
                'records' => $byDay->get($key, collect())->values()->all(),
            ];
        }

        return [
            'month' => $month->format('Y-m'), 'month_label' => $month->locale('es')->translatedFormat('F \\d\\e Y'),
            'previous_month' => $month->subMonth()->format('Y-m'), 'next_month' => $month->addMonth()->format('Y-m'),
            'today' => $today->toDateString(), 'selected_day' => $selectedDay,
            'selected_label' => $selected->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'total_records' => $records->count(), 'days' => $days,
            'selected_records' => $byDay->get($selectedDay, collect())->values()->all(),
        ];
    }

    /** Sanitize the frozen layout for display without reading current data. */
    public function viewSnapshot(ConsumoRecord $record): array
    {
        $snapshot = $record->snapshot;
        $pages = $snapshot['pages'] ?? [['id' => '00000000-0000-4000-8000-000000000001', 'title' => 'Hoja 1', 'grid_html' => $snapshot['grid_html'] ?? null]];
        $snapshot['pages'] = app(ConsumoGrid::class)->cleanPages($pages, $snapshot['suppliers'] ?? [], true);
        foreach ($snapshot['pages'] as &$page) {
            if ($page['grid_html'] !== null) {
                $page['grid_html'] = preg_replace('/\scontenteditable="[^"]*"/i', '', $page['grid_html']);
            }
        }
        unset($page);
        $snapshot['grid_html'] = $snapshot['pages'][0]['grid_html'] ?? null;
        $snapshot['import_metadata'] = $record->import_metadata;

        return $snapshot;
    }

    private function summary(ConsumoRecord $record): array
    {
        return [
            'id' => $record->id, 'sheet_id' => $record->sheet_id, 'sheet_version' => $record->sheet_version,
            'title' => $record->title, 'actor_name' => $record->actor_name,
            'registered_at' => $record->registered_at->utc()->toISOString(),
            'registered_time' => $record->registered_at->setTimezone(self::TIMEZONE)->format('H:i'),
            'date' => $record->registration_date->toDateString(),
            'import_file_name' => $record->import_metadata['file_name'] ?? null,
            'period_start' => $record->import_metadata['period_start'] ?? null,
            'period_end' => $record->import_metadata['period_end'] ?? null,
        ];
    }
}
