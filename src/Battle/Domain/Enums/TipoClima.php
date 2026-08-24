<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Representa los tipos de clima en una batalla.
 */
enum TipoClima: string
{
    case NONE = 'none';
    case SEQUIA = 'sequia';
    case DILUVIO = 'diluvio';
    case NIEBLA = 'niebla';
    case GRANIZO = 'granizo';
    case TORMENTA_ARENA = 'tormenta_arena';
    case TURBULENCIAS = 'turbulencias';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'sin clima',
            self::SEQUIA => 'sequía',
            self::DILUVIO => 'diluvio',
            self::NIEBLA => 'niebla',
            self::GRANIZO => 'granizo',
            self::TORMENTA_ARENA => 'tormenta de arena',
            self::TURBULENCIAS => 'turbulencias',
        };
    }

    public function esClimaActivo(): bool
    {
        return $this !== self::NONE;
    }
}
