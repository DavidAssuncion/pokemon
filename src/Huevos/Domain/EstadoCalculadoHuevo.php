<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

/**
 * Estado derivado de un huevo (GESTANDO/INCUBANDO/LISTO) más las horas
 * restantes hasta la siguiente transición. Objeto de valor inmutable;
 * el frontend recibe el shape {estado, restante_horas} vía serializadores.
 */
final readonly class EstadoCalculadoHuevo
{
    public function __construct(
        public EstadoHuevo $estado,
        public float $restanteHoras,
    ) {
    }
}
