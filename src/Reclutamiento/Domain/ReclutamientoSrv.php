<?php

namespace Src\Reclutamiento\Domain;

use App\Models\Reclutado;
use Illuminate\Database\Eloquent\Collection;

class ReclutamientoSrv
{
    public function __construct() {}

    public function obtenerPokemonsReclutados(): Collection
    {
        return Reclutado::with('pokemon')->get();
    }
}
