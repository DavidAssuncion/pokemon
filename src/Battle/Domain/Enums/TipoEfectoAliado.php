<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Efecto de apoyo que un movimiento puede aplicar sobre un aliado vivo.
 */
enum TipoEfectoAliado: string
{
    case ESCUDO_FISICO = 'escudo_fisico';
    case ESCUDO_ESPECIAL = 'escudo_especial';
    case AQUA_RING = 'aqua_ring';
    case CURAR_PS = 'curar_ps';
    case BUFF_STATS = 'buff_stats';
}
