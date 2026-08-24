<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOHabitatDetalle;

class ObtenerHabitatDetalle
{
    public function __construct(private HabitatRepositoryInterface $repository)
    {
    }

    public function handle(int $habitatId): DTOHabitatDetalle
    {
        return $this->repository->getHabitatDetail($habitatId);
    }
}
