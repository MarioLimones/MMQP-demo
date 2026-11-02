<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Fichaje extends Model
{
    public const TIMEZONE = 'Europe/Madrid';
    public const CLOCK_ORIGIN = 'confirmacion_admin';
    public const SELF_CLOCK_ORIGIN = 'confirmacion_empleado';
    private const UTC_ORIGINS = [self::CLOCK_ORIGIN, self::SELF_CLOCK_ORIGIN, 'tablet_kiosko'];

    protected $appends = ['entrada_local', 'salida_local'];

    public function usesUtcTimes(): bool
    {
        return in_array($this->origen, self::UTC_ORIGINS, true);
    }

    public function localEntry(): Carbon
    {
        return $this->usesUtcTimes() ? $this->entrada->copy()->timezone(self::TIMEZONE) : $this->entrada->copy();
    }

    public function localExit(): ?Carbon
    {
        return $this->salida ? ($this->usesUtcTimes() ? $this->salida->copy()->timezone(self::TIMEZONE) : $this->salida->copy()) : null;
    }

    public function getEntradaLocalAttribute(): string
    {
        return $this->localEntry()->toDateTimeString();
    }

    public function getSalidaLocalAttribute(): ?string
    {
        return $this->localExit()?->toDateTimeString();
    }

    /** Automatic clockings store UTC; historical manual entries store their displayed wall time. */
    public function scopeBetweenLocalDates(Builder $query, string $start, string $end): Builder
    {
        $first = Carbon::parse($start, self::TIMEZONE)->startOfDay();
        $after = Carbon::parse($end, self::TIMEZONE)->startOfDay()->addDay();

        return $query->where(fn ($days) => $days
            ->where(fn ($utc) => $utc->whereIn('origen', self::UTC_ORIGINS)
                ->where('entrada', '>=', $first->copy()->utc()->toDateTimeString())
                ->where('entrada', '<', $after->copy()->utc()->toDateTimeString()))
            ->orWhere(fn ($manual) => $manual->whereNotIn('origen', self::UTC_ORIGINS)
                ->where('entrada', '>=', $first->toDateTimeString())
                ->where('entrada', '<', $after->toDateTimeString())));
    }

    protected $fillable = [
        'user_id',
        'entrada',
        'salida',
        'horas_calculadas',
        'origen',
        'notas',
        'horario_id', 'turno_id', 'inicio_previsto', 'fin_previsto',
    ];

    protected $casts = [
        'entrada' => 'datetime',
        'salida' => 'datetime',
        'horas_calculadas' => 'decimal:2',
        'inicio_previsto' => 'datetime',
        'fin_previsto' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
