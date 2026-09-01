<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

/**
 * Calcula el nivel al que escalar a los rivales de un gimnasio en función del
 * nivel del jugador:
 *
 *     nivel_rival = nivel_min + floor((nivel_jugador - nivel_min) / 2)
 *
 * Solo aplica cuando nivel_jugador >= nivel_min; por debajo se queda en el
 * nivel mínimo (el gimnasio ya queda bloqueado por nivel).
 */
final class EscaladorNivelRival
{
    public function escalar(int $nivelMinimo, int $nivelJugador): int
    {
        $diferencia = max(0, $nivelJugador - $nivelMinimo);

        return $nivelMinimo + intdiv($diferencia, 2);
    }
}
