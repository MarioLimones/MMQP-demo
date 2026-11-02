<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumoImport extends Model
{
    protected $fillable = ['file_name', 'original_path', 'data', 'row_count', 'created_by'];

    protected $hidden = ['original_path'];

    protected function casts(): array
    {
        return ['data' => 'array', 'row_count' => 'integer'];
    }
}
