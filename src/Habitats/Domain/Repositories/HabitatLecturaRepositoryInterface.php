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

    /**
     * Nivel mínimo de jugador para acceder al nivel de hábitat indicado.
     * `null` si el nivel no tiene spawns o ninguno impone restricción.
     */
    public function minLevelForHabitatLevel(int $habitatId, int $nivel): ?int;

    /**
     * ¿Hay al menos un spawn del nivel disponible para el jugador? Un nivel
     * sin spawns no está disponible.
     */
    public function levelAvailableForPlayer(int $habitatId, int $nivel, int $nivelJugador): bool;

    public function getFamiliesByHabitat(int $habitatId): DTOFamiliasDisponibles;

    public function getUnassignedFamilies(): DTOFamiliasSinHabitat;
}
