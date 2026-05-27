<?php

namespace Src\Habitats\Domain\Repositories;

use Src\Habitats\Domain\ProvinciasCollection;

interface HabitatRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection;
    public function getPokemonsByHabitat(int $habitatId): array;
    public function getHabitatDetail(int $habitatId): array;
}
