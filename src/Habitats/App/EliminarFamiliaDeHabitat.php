<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatGestionRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliaEliminada;

class EliminarFamiliaDeHabitat
{
    public function __construct(private HabitatGestionRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId, int $evolutionChainId): DTOFamiliaEliminada
    {
        return $this->repository->removeFamily($habitatId, $evolutionChainId);
    }
}
