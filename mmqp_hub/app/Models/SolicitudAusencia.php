<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudAusencia extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'fecha',
        'fecha_fin',
        'tipo',
        'motivo_empleado',
        'estado',
        'razon_admin',
        'request_key',
        'request_hash',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_fin' => 'date',
        'version' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDatesRange()
    {
        $startRaw = substr((string) ($this->attributes['fecha'] ?? ''), 0, 10);
        $endRaw = substr((string) ($this->attributes['fecha_fin'] ?? $startRaw), 0, 10);
        if (!\App\Services\AbsenceAvailability::validDate($startRaw) || !\App\Services\AbsenceAvailability::validDate($endRaw)) {
            return [];
        }
        if (!$this->fecha || ($this->fecha_fin && $this->fecha_fin->lt($this->fecha))) {
            return [];
        }
        $dates = [];
        $start = $this->fecha->copy();
        $end = $this->fecha_fin ? $this->fecha_fin->copy() : $this->fecha->copy();

        while ($start->lte($end)) {
            $dates[] = $start->format('Y-m-d');
            $start->addDay();
        }

        return $dates;
    }
}
