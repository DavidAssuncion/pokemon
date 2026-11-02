<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatGestionRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliaDisponible;

class AsignarFamiliaAHabitat
{
    public function __construct(private HabitatGestionRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId, int $evolutionChainId): DTOFamiliaDisponible
    {
        return $this->repository->assignFamily($habitatId, $evolutionChainId);
    }
}
