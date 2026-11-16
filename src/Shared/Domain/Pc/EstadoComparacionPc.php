<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Pc;

/**
 * Estado de disponibilidad de una comparación de PC antes del combate.
 *
 * Los estados de dominio no son errores HTTP: el indicador de PC nunca debe
 * bloquear el combate, por lo que el frontend los presenta como avisos.
 */
enum EstadoComparacionPc: string
{
    case OK = 'ok';
    case EQUIPO_INCOMPLETO = 'equipo_incompleto';
    case SIN_RIVALES = 'sin_rivales';
    case NO_DISPONIBLE = 'no_disponible';
}
