<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\DataTransferObjects;

use Src\Habitats\Presentation\ColeccionPokemonFamilia;
use Src\Habitats\Presentation\DTOPokemonFamilia;

/**
 * Familia dividida entre el primer integrante (menor species_id) y el resto
 * de evoluciones.
 */
final readonly class RepartoFamilia
{
    public function __construct(
        public readonly ?DTOPokemonFamilia $base,
        public readonly ColeccionPokemonFamilia $evoluciones,
    ) {
    }
}
