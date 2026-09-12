<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\DataTransferObjects;

use Src\CombateEntrenadores\Domain\Collections\EstadoEntrenadorCollection;

/**
 * Los 3 entrenadores de un nivel de hábitat con su estado de desbloqueo.
 */
final readonly class EntrenadoresNivel
{
    public function __construct(
        public readonly int $nivel,
        public readonly EstadoEntrenadorCollection $entrenadores,
    ) {
    }
}
