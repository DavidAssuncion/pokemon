<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

final class EntrenadorDerrotadoHoy extends ViolacionReglaNegocio
{
    public function __construct()
    {
        parent::__construct('Ya has derrotado a este entrenador hoy.');
    }
}
