<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain;

/**
 * Reglas de diseño de las mazmorras.
 *
 * Cada piso es un combate 3v1 contra un jefe con vida ×10 y un efecto
 * permanente ×3 sobre sus estadísticas de combate (no es una etapa). Tras una
 * derrota, el piso queda bloqueado durante una hora.
 */
final class ReglasMazmorra
{
    /** Vida del jefe: ×10 sobre la vida base. */
    public const MULTIPLICADOR_HP_JEFE = 10;

    /**
     * Efecto permanente del jefe sobre ataque, defensa, ataque especial,
     * defensa especial, velocidad y barreras: ×3 (+200 %). No es una etapa.
     */
    public const MULTIPLICADOR_STATS_EFECTIVO_JEFE = 3.0;

    /** La recompensa de mazmorra es 7× la recompensa de un gimnasio. */
    public const FACTOR_RECOMPENSA_RESPECTO_GIMNASIO = 7;

    public const HORAS_COOLDOWN = 1;

    public const PISO_MIN = 1;
}
