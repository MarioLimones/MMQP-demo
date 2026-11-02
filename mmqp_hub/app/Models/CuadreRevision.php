<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CuadreRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['snapshot' => 'array', 'before_snapshot' => 'array', 'actor_snapshot' => 'array', 'recorded_at' => 'immutable_datetime', 'version' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las revisiones de caja son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las revisiones de caja son inmutables.'));
    }

    public function cuadre()
    {
        return $this->belongsTo(CuadreCaja::class, 'cuadre_id');
    }
}
