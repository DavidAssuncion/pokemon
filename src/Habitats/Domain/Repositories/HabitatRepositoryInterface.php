<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Repositories;

use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Presentation\DTOHabitatDetalle;

interface HabitatRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection;

    public function getPokemonsByHabitat(int $habitatId): array;

    public function getHabitatDetail(int $habitatId): DTOHabitatDetalle;
}
