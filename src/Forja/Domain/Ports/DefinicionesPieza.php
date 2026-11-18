<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Ports;

use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Piezas\Collections\NidosConstruidosCollection;

/**
 * Puerto de lectura de las definiciones de pieza de las familias con nido.
 *
 * Forja no conoce las tablas de Pokémon, stats ni evoluciones: recibe una
 * proyección tipada ya resuelta (etapa, gate y bonos) a partir de los nidos.
 */
interface DefinicionesPieza
{
    public function definiciones(NidosConstruidosCollection $nidos): DefinicionesPiezaCollection;
}
