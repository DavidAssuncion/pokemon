<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Repositories;

use Src\Habitats\Domain\Collections\PokemonBasicoCollection;
use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;
use Src\Habitats\Presentation\DTOHabitatDetalle;

/**
 * Lectura de provincias, hábitats y familias (sin mutaciones).
 */
interface HabitatLecturaRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection;

    public function getPokemonsByHabitat(int $habitatId): PokemonBasicoCollection;

    public function getHabitatDetail(int $habitatId): DTOHabitatDetalle;

    public function getFamiliesByHabitat(int $habitatId): DTOFamiliasDisponibles;

    public function getUnassignedFamilies(): DTOFamiliasSinHabitat;
}
