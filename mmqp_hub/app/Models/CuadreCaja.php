<?php

namespace App\Models;

use App\Services\CuadreCalculator;
use Illuminate\Database\Eloquent\Model;

class CuadreCaja extends Model
{
    protected $fillable = [
        'fecha',
        'turno',
        'monedas',
        'billetes',
        'monedas_ingreso',
        'billetes_ingreso',
        'blisters',
        'blisters_ingreso',
        'nota_blanca',
        'total_calculado',
        'fondo_fijo',
        'ingreso_real',
        'descuadre',
        'gastos',
        'cambios',
        'repartos',
        'pedidos_hora_exacta',
        'otros',
        'venta_parcial',
        'created_by',
        'responsible_id',
        'version',
        'calculation_snapshot',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monedas' => 'array',
        'billetes' => 'array',
        'monedas_ingreso' => 'array',
        'billetes_ingreso' => 'array',
        'blisters' => 'array',
        'blisters_ingreso' => 'array',
        'nota_blanca' => 'decimal:2',
        'total_calculado' => 'decimal:2',
        'fondo_fijo' => 'decimal:2',
        'ingreso_real' => 'decimal:2',
        'descuadre' => 'decimal:2',
        'gastos' => 'decimal:2',
        'cambios' => 'decimal:2',
        'repartos' => 'decimal:2',
        'pedidos_hora_exacta' => 'decimal:2',
        'otros' => 'decimal:2',
        'venta_parcial' => 'decimal:2',
        'version' => 'integer',
        'calculation_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function revisions()
    {
        return $this->hasMany(CuadreRevision::class, 'cuadre_id')->orderByDesc('version');
    }

    public function setFechaAttribute($value): void
    {
        // Avoid midnight timestamps in SQLite DATE columns while retaining the date cast for reading.
        $this->attributes['fecha'] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    /** Canonical cents in JSON avoid SQLite's floating NUMERIC affinity for new versions. */
    public function getAttribute($key)
    {
        if (in_array($key, [...CuadreCalculator::MONEY_FIELDS, 'total_calculado', 'ingreso_real', 'descuadre'], true)) {
            $snapshot = parent::getAttribute('calculation_snapshot');
            $cents = $snapshot['result_cents'][$key] ?? $snapshot['input_cents'][$key] ?? null;
            if ($cents !== null) {
                return CuadreCalculator::decimal($cents);
            }
        }

        return parent::getAttribute($key);
    }
}
