<?php

namespace App\Services;

use RuntimeException;

class CuadreConflict extends RuntimeException
{
    public function __construct(public ?int $currentVersion, string $message = 'Otro usuario ha guardado este cierre. Consulta la versión vigente, compara los cambios y recarga antes de rectificar.')
    {
        parent::__construct($message);
    }
}
