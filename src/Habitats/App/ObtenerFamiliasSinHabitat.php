<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;

class ObtenerFamiliasSinHabitat
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(): DTOFamiliasSinHabitat
    {
        return $this->repository->getUnassignedFamilies();
    }
}
