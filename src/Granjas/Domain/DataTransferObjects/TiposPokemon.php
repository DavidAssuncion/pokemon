<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Habitats\Presentation\ColeccionTiposFamilia;

/**
 * Tipos conocidos de un pokémon concreto (solo cuando el jugador lo ha visto).
 */
final readonly class TiposPokemon
{
    public function __construct(
        public int $pokemonId,
        public ColeccionTiposFamilia $tipos,
    ) {
    }
}
