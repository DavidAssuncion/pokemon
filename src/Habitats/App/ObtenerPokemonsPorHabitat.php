<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Collections\PokemonBasicoCollection;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;

class ObtenerPokemonsPorHabitat
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId): PokemonBasicoCollection
    {
        return $this->repository->getPokemonsByHabitat($habitatId);
    }
}
