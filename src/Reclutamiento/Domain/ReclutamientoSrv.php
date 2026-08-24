<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain;

class ReclutamientoSrv
{
    public function __construct(
        private readonly ReclutamientoRepositoryInterface $reclutamientoRepository,
    ) {
    }

    /** @return ReclutadoEntity[] */
    public function obtenerPokemonsReclutados(): array
    {
        return $this->reclutamientoRepository->obtenerTodos();
    }
}
