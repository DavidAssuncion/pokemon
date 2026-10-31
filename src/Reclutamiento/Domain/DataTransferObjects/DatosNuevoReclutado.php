<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\DataTransferObjects;

use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Datos mínimos para crear un reclutado. Los EVs de granja viajan aparte para
 * mantener la firma del caso de uso en 2 parámetros.
 */
final readonly class DatosNuevoReclutado
{
    public function __construct(
        public int $userId,
        public int $pokemonId,
        public ?StatsValue $ivs = null,
        public bool $esShiny = false,
    ) {
    }
}
