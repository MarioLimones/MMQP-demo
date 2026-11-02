<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumoProductLink extends Model
{
    protected $fillable = ['document_id', 'product_id', 'sources'];

    protected function casts(): array
    {
        return ['document_id' => 'integer', 'sources' => 'array'];
    }
}
