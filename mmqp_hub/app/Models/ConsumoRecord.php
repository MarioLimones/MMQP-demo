<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ConsumoRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sheet_id', 'sheet_version', 'registered_at', 'registration_date',
        'actor_id', 'actor_name', 'title', 'import_metadata', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'sheet_id' => 'integer', 'sheet_version' => 'integer', 'actor_id' => 'integer',
            'registered_at' => 'immutable_datetime', 'registration_date' => 'immutable_date',
            'import_metadata' => 'array', 'snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de consumo se conservan sin sobrescribirlos.'));
        static::deleting(fn () => throw new LogicException('Los registros de consumo se conservan sin eliminarlos.'));
    }
}
