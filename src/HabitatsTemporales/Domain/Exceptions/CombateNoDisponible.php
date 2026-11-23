<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * El combate solicitado no es el combate actual de la oleada.
 */
final class CombateNoDisponible extends ViolacionReglaNegocio
{
    public function __construct()
    {
        parent::__construct('Ese combate no está disponible todavía.');
    }
}
