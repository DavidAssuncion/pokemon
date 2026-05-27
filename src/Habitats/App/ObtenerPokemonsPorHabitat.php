<?php

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;

class ObtenerPokemonsPorHabitat
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId): array
    {
        return $this->repository->getPokemonsByHabitat($habitatId);
    }
}
