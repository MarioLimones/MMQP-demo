<?php

namespace App\Console\Commands;

use App\Models\AvailabilityBlock;
use App\Models\LegacyAvailabilityImport;
use App\Models\SolicitudAusencia;
use App\Models\User;
use App\Services\AbsenceAvailability;
use App\Services\AbsenceService;
use App\Services\PublishedAbsenceConflicts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileAbsences extends Command
{
    protected $signature = 'ausencias:reconciliar
        {--apply : Aplicar la importación conservadora; por defecto solo diagnóstico}
        {--json : Emitir el informe completo como JSON}
        {--user= : Limitar a un empleado}
        {--release-block= : Retirar un bloqueo histórico tras revisar su origen}
        {--confirm-block= : Confirmar un bloqueo histórico ajeno a las solicitudes}
        {--note= : Justificación de la revisión manual}';

    protected $description = 'Diagnostica y reconcilia ausencias sin inventar aprobaciones ni borrar causas desconocidas';

    public function handle(AbsenceAvailability $availability, AbsenceService $service): int
    {
        $reviewId = $this->option('release-block') ?: $this->option('confirm-block');
        if (($this->option('release-block') && $this->option('confirm-block')) ||
            ($reviewId && (! ctype_digit((string) $reviewId) || ! trim((string) $this->option('note')))) ||
            ($this->option('user') && ! ctype_digit((string) $this->option('user')))) {
            $this->error('Indica un identificador válido, una sola acción y --note con la justificación de la revisión.');

            return self::FAILURE;
        }
        $review = $reviewId ? AvailabilityBlock::find($reviewId) : null;
        if ($reviewId && (! $review || ($this->option('user') && $review->user_id != $this->option('user')))) {
            $this->error('No existe ese bloqueo en el ámbito indicado.');

            return self::FAILURE;
        }
        $query = User::query();
        if ($this->option('user')) {
            $query->whereKey($this->option('user'));
        }
        $report = ['mode' => $this->option('apply') ? 'apply' : 'diagnostic', 'imports' => 0,
            'blocks_created' => 0, 'review_changes' => 0, 'users' => []];

        foreach ($query->orderBy('id')->get() as $user) {
            $row = $this->diagnose($user, $availability);
            if (! $user->legacyAvailabilityImport) {
                $report['imports']++;
                $report['blocks_created'] += count($row['proposed_blocks']);
                if ($this->option('apply')) {
                    DB::transaction(function () use ($user, $service, $availability) {
                        $locked = $service->lockUser($user->id);
                        if (LegacyAvailabilityImport::where('user_id', $user->id)->exists()) {
                            return;
                        }
                        // Preserve the exact original strings, including malformed/uninterpretable data.
                        LegacyAvailabilityImport::create([
                            'user_id' => $locked->id,
                            'vacaciones_original' => $locked->getRawOriginal('vacaciones'),
                            'fechas_libres_original' => $locked->getRawOriginal('fechas_libres_solicitadas'),
                        ]);
                        foreach ($availability->legacyDates($locked) as $block) {
                            AvailabilityBlock::create($block + ['user_id' => $locked->id, 'status' => 'pending_review']);
                        }
                    }, 5);
                }
            }
            $report['users'][] = $row;
        }
        if ($review) {
            $target = $this->option('release-block') ? 'released' : 'confirmed';
            $report['review'] = ['block_id' => $review->id, 'status' => $target, 'note' => $this->option('note')];
            if ($review->status !== $target || $review->review_note !== $this->option('note')) {
                $report['review_changes'] = 1;
                if ($this->option('apply')) {
                    DB::transaction(function () use ($review, $target, $service) {
                        $service->lockUser($review->user_id);
                        AvailabilityBlock::whereKey($review->id)->update([
                            'status' => $target, 'review_note' => $this->option('note'), 'updated_at' => now(),
                        ]);
                    }, 5);
                }
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($report['mode'] === 'apply' ? 'Reconciliación aplicada.' : 'Diagnóstico sin escritura.');
            $this->table(['Empleado', 'Vacaciones omitidas', 'Bloqueos omitidos', 'Históricos por revisar', 'Intervalos inválidos', 'Solapamientos'],
                array_map(fn ($row) => [$row['user_id'], count($row['missing_vacation_dates']), count($row['missing_block_dates']),
                    count($row['historical_blocks']), count($row['invalid_absences']), count($row['overlaps'])], $report['users']));
            $this->line("Importaciones: {$report['imports']}; bloqueos históricos: {$report['blocks_created']}; revisiones: {$report['review_changes']}.");
            $this->line('Usa --json para revisar fechas, originales inválidos, causas superpuestas y conflictos con horarios publicados.');
        }

        return self::SUCCESS;
    }

    private function diagnose(User $user, AbsenceAvailability $availability): array
    {
        $legacy = $availability->legacyDates($user);
        $vacations = $availability->vacationDates($user);
        $approved = $user->ausenciasVigentes->flatMap(fn ($absence) => $absence->getDatesRange())->all();
        $row = ['user_id' => $user->id,
            'missing_vacation_dates' => array_values(array_diff($vacations, array_column(array_filter($legacy, fn ($item) => $item['source'] === 'vacaciones'), 'fecha'))),
            'missing_block_dates' => array_values(array_diff(array_unique($approved), array_column($legacy, 'fecha'))),
            'historical_blocks' => $user->legacyAvailabilityImport ? $user->availabilityBlocks->where('status', 'pending_review')->values()->toArray() : $legacy,
            'proposed_blocks' => $user->legacyAvailabilityImport ? [] : $legacy,
            'invalid_legacy_values' => [], 'invalid_absences' => [], 'overlaps' => [],
            'published_conflicts' => app(PublishedAbsenceConflicts::class)->forUser($user)];

        foreach (['vacaciones', 'fechas_libres_solicitadas'] as $source) {
            $raw = $user->getRawOriginal($source);
            if ($raw === null) {
                continue;
            }
            $values = $availability->legacyValues($raw);
            if ($values === null) {
                $row['invalid_legacy_values'][] = ['source' => $source, 'original' => $raw];

                continue;
            }
            foreach ($values as $value) {
                if (! AbsenceAvailability::validDate($value)) {
                    $row['invalid_legacy_values'][] = ['source' => $source, 'original' => $value];
                }
            }
        }
        $activeDates = [];
        foreach (SolicitudAusencia::withTrashed()->where('user_id', $user->id)->get() as $absence) {
            try {
                $start = substr((string) $absence->getRawOriginal('fecha'), 0, 10);
                $end = substr((string) ($absence->getRawOriginal('fecha_fin') ?? $start), 0, 10);
                if (! AbsenceAvailability::validDate($start) || ! AbsenceAvailability::validDate($end) || $start > $end ||
                    ! in_array($absence->tipo, ['vacaciones', 'dia_libre', 'baja'], true) ||
                    ! in_array($absence->estado, ['pendiente', 'aprobada', 'rechazada', 'cancelada'], true)) {
                    $row['invalid_absences'][] = $absence->id;

                    continue;
                }
                if ($absence->estado === 'aprobada' && ! $absence->trashed()) {
                    foreach ($absence->getDatesRange() as $date) {
                        $activeDates[$date][] = $absence->id;
                    }
                }
            } catch (\Throwable) {
                $row['invalid_absences'][] = $absence->id;
            }
        }
        foreach ($activeDates as $date => $ids) {
            if (count($ids) > 1) {
                $row['overlaps'][] = ['fecha' => $date, 'absence_ids' => $ids];
            }
        }

        return $row;
    }
}
