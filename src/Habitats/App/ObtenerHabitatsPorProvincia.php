<?php

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Domain\ProvinciasCollection;

class ObtenerHabitatsPorProvincia
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(): ProvinciasCollection
    {
        return $this->repository->allProvinciasWithHabitats();
    }
}
