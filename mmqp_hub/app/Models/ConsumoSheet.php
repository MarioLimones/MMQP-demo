<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumoSheet extends Model
{
    protected $fillable = ['title', 'import_id', 'document_id', 'document_version', 'payload', 'version', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer', 'import_id' => 'integer', 'document_version' => 'integer'];
    }

    public function report()
    {
        return $this->belongsTo(ConsumoImport::class, 'import_id');
    }
}
