<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Calcula el nivel al que escalar a los rivales en función del nivel del
 * jugador (dominio puro, compartido entre módulos):
 *
 *     nivel_rival = nivel_base + floor((nivel_jugador - nivel_base) / 2)
 *
 * Solo aplica cuando nivel_jugador >= nivel_base; por debajo se queda en el
 * nivel base (el acceso ya queda bloqueado por nivel mínimo).
 */
final class EscaladorNivelRival
{
    public function escalar(int $nivelBase, int $nivelJugador): int
    {
        $diferencia = max(0, $nivelJugador - $nivelBase);

        return $nivelBase + intdiv($diferencia, 2);
    }
}
