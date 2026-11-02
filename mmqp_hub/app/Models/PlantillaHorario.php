<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlantillaHorario extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'turnos'];

    protected $casts = [
        'turnos' => 'array',
    ];
}
