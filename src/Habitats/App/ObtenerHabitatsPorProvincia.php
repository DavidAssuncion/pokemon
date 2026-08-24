<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;

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
