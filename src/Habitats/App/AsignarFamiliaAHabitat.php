<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliaAsignada;

class AsignarFamiliaAHabitat
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId, int $evolutionChainId): DTOFamiliaAsignada
    {
        return $this->repository->assignFamily($habitatId, $evolutionChainId);
    }
}
