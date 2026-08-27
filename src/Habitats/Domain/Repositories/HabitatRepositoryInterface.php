<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Repositories;

use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Presentation\DTOFamiliaAsignada;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;
use Src\Habitats\Presentation\DTOHabitatDetalle;

interface HabitatRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection;

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getPokemonsByHabitat(int $habitatId): array;

    public function getHabitatDetail(int $habitatId): DTOHabitatDetalle;

    public function getFamiliesByHabitat(int $habitatId): DTOFamiliasDisponibles;

    public function getUnassignedFamilies(): DTOFamiliasSinHabitat;

    public function assignFamily(int $habitatId, int $evolutionChainId): DTOFamiliaAsignada;

    public function removeFamily(int $habitatId, int $evolutionChainId): DTOFamiliaEliminada;

    /**
     * @return array<int, int>
     */
    public function getFamilyPokemonsByChain(int $evolutionChainId): array;
}
