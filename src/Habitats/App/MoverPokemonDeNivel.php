<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOPokemonNivelActualizado;

class MoverPokemonDeNivel
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId, int $pokemonId, int $level): DTOPokemonNivelActualizado
    {
        return $this->repository->movePokemonToLevel($habitatId, $pokemonId, $level);
    }
}
