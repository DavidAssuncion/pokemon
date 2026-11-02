<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Repositories;

use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
use Src\Habitats\Presentation\DTOPokemonNivelActualizado;

/**
 * Mutaciones de gestión de familias en hábitats (asignar, quitar, mover).
 */
interface HabitatGestionRepositoryInterface
{
    public function assignFamily(int $habitatId, int $evolutionChainId): DTOFamiliaDisponible;

    public function removeFamily(int $habitatId, int $evolutionChainId): DTOFamiliaEliminada;

    public function movePokemonToLevel(int $habitatId, int $pokemonId, int $level): DTOPokemonNivelActualizado;
}
