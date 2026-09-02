<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AgregadoBatalla;

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

    public function evaluar(AgregadoBatalla $batalla, string $equipoActor): float
    {
        $miEquipo = $equipoActor === 'team1' ? $batalla->team1 : $batalla->team2;
        $equipoRival = $equipoActor === 'team1' ? $batalla->team2 : $batalla->team1;

        $vivosMiEquipo = $miEquipo->combatientesVivos();
        $vivosRival = $equipoRival->combatientesVivos();

        $score = 0.0;

        // ─── Ventaja numérica ───
        $diferencia = count($vivosMiEquipo) - count($vivosRival);
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
