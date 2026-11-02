<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HorarioSemanal extends Model
{
    use HasFactory;

    protected $table = 'horarios_semanales';

    protected $fillable = [
        'semana_id',
        'fecha_inicio',
        'fecha_fin',
        'plantilla_diseno',
        'resultados',
        'roles_publicados',
        'empleados_snapshot',
        'turnos_cubiertos',
        'turnos_totales',
        'generado_por',
        'estado', 'version', 'vigente', 'draft_slot', 'source_publicacion_id',
        'published_draft_version', 'published_at', 'last_save_key', 'last_save_hash',
        'transfer_hash',
    ];

    protected $casts = [
        'plantilla_diseno'   => 'array',
        'resultados'         => 'array',
        'roles_publicados'   => 'array',
        'empleados_snapshot' => 'array',
        'version' => 'integer',
        'vigente' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopeVigentes($query)
    {
        // Historical imports and editable drafts never become operational schedules.
        return $query->whereNotIn('estado', ['borrador', 'historico'])->where('vigente', true);
    }

    protected static function booted(): void
    {
        static::updating(function (self $horario) {
            if ($horario->getOriginal('estado') !== 'borrador' && $horario->isDirty([
                'semana_id', 'fecha_inicio', 'fecha_fin', 'plantilla_diseno', 'resultados',
                'empleados_snapshot', 'turnos_cubiertos', 'turnos_totales', 'generado_por',
                'estado', 'draft_slot',
            ])) {
                throw new \LogicException('Prepare un borrador independiente para editar este horario.');
            }
        });
    }

    public function generador()
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function getIsImportadoAttribute()
    {
        $firstEmpleado = $this->empleados_snapshot[0]['id'] ?? null;
        return $firstEmpleado && str_starts_with((string)$firstEmpleado, 'hist_');
    }
}
