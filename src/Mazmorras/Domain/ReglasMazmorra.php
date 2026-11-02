<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain;

/**
 * Reglas de diseño de las mazmorras.
 *
 * Cada piso es un combate 3v1 contra un jefe con stats multiplicados. Tras una
 * derrota, el piso queda bloqueado durante una hora.
 */
final class ReglasMazmorra
{
    /** Resto de estadísticas del jefe (no HP): ×10. */
    public const MULTIPLICADOR_STATS_JEFE = 10;

    /** Vida del jefe: ×7 sobre la vida base (decisión de producto 2026-10-31). */
    public const MULTIPLICADOR_HP_JEFE = 7;

    /** Bonus inicial del jefe: +30 % a todas las estadísticas al empezar el combate. */
    public const BONUS_INICIAL_STATS_JEFE = 30.0;

    /** La recompensa de mazmorra es 7× la recompensa de un gimnasio. */
    public const FACTOR_RECOMPENSA_RESPECTO_GIMNASIO = 7;

    public const HORAS_COOLDOWN = 1;

    public const PISO_MIN = 1;
}
