<?php

namespace App\Exceptions;

use App\Models\Resultado;
use RuntimeException;

class EvaluacionConcurrencyException extends RuntimeException
{
    public function __construct(
        private readonly Resultado $resultado,
        string $message = 'La evaluación fue actualizada por otra sesión. Recarga el formulario antes de guardar de nuevo.'
    ) {
        parent::__construct($message);
    }

    public function resultado(): Resultado
    {
        return $this->resultado;
    }
}
