<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * La oleada no está desbloqueada por medallas o por la oleada anterior.
 */
final class OleadaNoDisponible extends ViolacionReglaNegocio
{
    public function __construct(string $motivo)
    {
        parent::__construct($motivo);
    }
}
