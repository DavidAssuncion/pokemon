<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;

class ObtenerFamiliasDisponibles
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId): DTOFamiliasDisponibles
    {
        return $this->repository->getFamiliesByHabitat($habitatId);
    }
}
