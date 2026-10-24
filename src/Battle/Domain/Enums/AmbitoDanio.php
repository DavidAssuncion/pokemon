<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Lado del combate al que se aplica un modificador de daño.
 */
enum AmbitoDanio: string
{
    case INFLIGIDO = 'infligido';
    case RECIBIDO = 'recibido';
}
