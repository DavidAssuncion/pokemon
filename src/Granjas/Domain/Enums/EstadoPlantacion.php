<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Enums;

/**
 * Estado calculado de una plantación. No existe estado "agotada": la planta
 * persiste tras cosechar y el stock se recalcula al leer.
 */
enum EstadoPlantacion: string
{
    case CRECIENDO = 'creciendo';
    case LISTA = 'lista';
}
