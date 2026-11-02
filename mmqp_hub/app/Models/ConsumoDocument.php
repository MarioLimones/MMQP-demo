<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumoDocument extends Model
{
    protected $fillable = ['scope', 'title', 'payload', 'version', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer'];
    }

    public function links()
    {
        return $this->hasMany(ConsumoProductLink::class, 'document_id');
    }
}
