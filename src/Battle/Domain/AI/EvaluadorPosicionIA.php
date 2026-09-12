<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\Bando;

/**
 * Evalúa la posición global de la batalla para el equipo del actor.
 * Retorna un score: positivo = ventaja, negativo = desventaja.
 */
class EvaluadorPosicionIA
{
    public function __construct(
        private readonly PesosAmenaza $pesos,
    ) {
    }

    public function evaluar(AgregadoBatalla $batalla, Bando $equipoActor): float
    {
        $miEquipo = $equipoActor === Bando::UNO ? $batalla->team1 : $batalla->team2;
        $equipoRival = $equipoActor === Bando::UNO ? $batalla->team2 : $batalla->team1;

        $vivosMiEquipo = $miEquipo->combatientesCollection()->vivos();
        $vivosRival = $equipoRival->combatientesCollection()->vivos();

        $score = 0.0;

        // ─── Ventaja numérica ───
        $diferencia = $vivosMiEquipo->count() - $vivosRival->count();
        $score += $diferencia * $this->pesos->puntosVentajaNumerica;

        // ─── Estado del HP aliado ───
        foreach ($vivosMiEquipo as $aliado) {
            $hpPct = $aliado->pokemon()->battleStats()->hp > 0
                ? $aliado->hpActual() / $aliado->pokemon()->battleStats()->hp
                : 0;

            if ($hpPct > 0.5) {
                $score += $this->pesos->puntosAliadoSano;
            } elseif ($hpPct < 0.25) {
                $score += $this->pesos->puntosAliadoHerido;
            }
        }

        return $score;
    }
}
