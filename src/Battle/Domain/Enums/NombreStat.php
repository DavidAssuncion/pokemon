<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Nombres canónicos de las estadísticas de batalla.
 */
enum NombreStat: string
{
    case ATTACK = 'attack';
    case DEFENSE = 'defense';
    case SP_ATK = 'spAtk';
    case SP_DEF = 'spDef';
    case SPEED = 'speed';
}
