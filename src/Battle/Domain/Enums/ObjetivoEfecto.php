<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Destinatarios de un daño o curación de fin de turno.
 */
enum ObjetivoEfecto: string
{
    case PROPIO = 'propio';
    case EQUIPO = 'equipo';
    case ENEMIGOS = 'enemigos';
    case ENEMIGOS_DORMIDOS = 'enemigos_dormidos';
    case ESCUDOS_ENEMIGOS = 'escudos_enemigos';
}
