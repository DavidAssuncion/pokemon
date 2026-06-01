<?php

namespace Src\Reclutamiento\App;

use Illuminate\Database\Eloquent\Collection;
use Src\Reclutamiento\Domain\ReclutamientoSrv;

class ObtenerPokemonsReclutados
{
    public function __construct(
        public readonly ReclutamientoSrv $reclutamientoSrv,
    ) {}

    public function run(): Collection
    {
        return $this->reclutamientoSrv->obtenerPokemonsReclutados();
    }
}
