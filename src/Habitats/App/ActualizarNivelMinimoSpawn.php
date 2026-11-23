<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatGestionRepositoryInterface;
use Src\Habitats\Presentation\DTOMinLevelActualizado;

/**
 * Fija manualmente el nivel mínimo de jugador de un spawn de hábitat.
 */
final class ActualizarNivelMinimoSpawn
{
    public function __construct(private readonly HabitatGestionRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId, int $pokemonId, int $minLevel, bool $manual): DTOMinLevelActualizado
    {
        return $this->repository->updateMinLevel($habitatId, $pokemonId, $minLevel, $manual);
    }

    public function automatico(int $habitatId, int $pokemonId): DTOMinLevelActualizado
    {
        return $this->repository->resetMinLevel($habitatId, $pokemonId);
    }
}
