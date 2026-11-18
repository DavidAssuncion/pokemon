<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Ports;

use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Shared\Collections\IntCollection;

/**
 * Puerto de lectura de definiciones de pieza por especie.
 *
 * Permite resolver una pieza ya poseída (nombre, icono, stats y habilidad) a
 * partir de su clave, sin depender de que el jugador conserve el nido.
 */
interface PiezasPorEspecie
{
    public function definicionesDeEspecies(IntCollection $speciesIds): DefinicionesPiezaCollection;
}
