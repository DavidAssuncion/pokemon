<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App;

use Src\Reclutamiento\Domain\ReclutamientoRepositoryInterface;

class ObtenerPokemonsReclutados
{
    public function __construct(
        private readonly ReclutamientoRepositoryInterface $reclutamientoRepository,
    ) {
    }

    /** @return array */
    public function run(): array
    {
        return $this->reclutamientoRepository->obtenerTodos();
    }
}
