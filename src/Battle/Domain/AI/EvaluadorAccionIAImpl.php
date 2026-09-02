<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Illuminate\Support\Collection;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAccion;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAmenaza;
use Src\Battle\Domain\Combatiente;

/**
 * Evalúa cada acción candidata según KO, daño, reducción de amenaza, supervivencia y riesgo.
 * Usa CalculadoraDanioIA (DRY) y PesosAmenaza (escalable).
 */
class EvaluadorAccionIAImpl implements EvaluadorAccionIA
{
    public function __construct(
        private readonly CalculadoraDanioIA $calculadoraDanio,
        private readonly PesosAmenaza $pesos,
    ) {
    }

    public function evaluar(
        ContextoDecisionIA $contexto,
        Collection $amenazas,
        AccionBatalla $accion,
    ): EvaluacionAccion {
        $movimiento = $accion->move;
        $objetivo = $accion->defender;

        // --- KO Value ---
        $koValue = 0.0;
        if (! $movimiento->esEstado()) {
            $estimacion = $this->calculadoraDanio->estimar(
                $accion->attacker,
                $objetivo,
                $movimiento,
                $contexto->battle,
            );
            if ($estimacion->probabilidadKO > 0) {
                $koValue = $this->pesos->puntosKO;
            }
        }

        // --- Damage Value ---
        $damageValue = 0.0;
        if (! $movimiento->esEstado()) {
            $estimacion = $this->calculadoraDanio->estimar(
                $accion->attacker,
                $objetivo,
                $movimiento,
                $contexto->battle,
            );
            $hpEfectivo = $this->calculadoraDanio->calcularHPEfectivo($objetivo, $movimiento);
            if ($hpEfectivo > 0) {
                $damageValue = ($estimacion->esperado / $hpEfectivo) * $this->pesos->multiplicadorDanio;
            }
        }

        // --- Threat Reduction ---
        $threatReduction = $this->calcularReduccionAmenaza($amenazas, $objetivo, $koValue);

        // --- Survival Value ---
        $survivalValue = $this->calcularSupervivencia($contexto, $objetivo, $koValue);

        // --- Risk ---
        $risk = $this->calcularRiesgo($contexto);

        // --- Score ---
        $score = $koValue + $damageValue + $threatReduction + $survivalValue - $risk;

        return new EvaluacionAccion(
            accion: $accion,
            score: $score,
            koValue: $koValue,
            damageValue: $damageValue,
            threatReduction: $threatReduction,
            survivalValue: $survivalValue,
            risk: $risk,
        );
    }

    /**
     * Si el objetivo muere, el score de amenaza de ese enemigo se suma como reducción.
     */
    private function calcularReduccionAmenaza(Collection $amenazas, Combatiente $objetivo, float $koValue): float
    {
        if ($koValue <= 0) {
            return 0.0;
        }

        /** @var EvaluacionAmenaza $amenaza */
        foreach ($amenazas as $amenaza) {
            if ($amenaza->enemigo->id() === $objetivo->id()) {
                return $amenaza->score;
            }
        }

        return 0.0;
    }

    /**
     * Evalúa si el actor sobrevive después de ejecutar esta acción.
     */
    private function calcularSupervivencia(
        ContextoDecisionIA $contexto,
        Combatiente $objetivo,
        float $koValue,
    ): float {
        $actor = $contexto->actor;

        // Si el objetivo muere, no puede contraatacar
        $enemigosRestantes = $contexto->enemigos;
        if ($koValue > 0) {
            $enemigosRestantes = $contexto->enemigos->filter(
                fn ($e) => $e->id() !== $objetivo->id()
            )->values();
        }

        $hpEfectivoActor = $actor->hpActual()
            + $actor->defensaHpActual()
            + $actor->defensaEspHpActual();

        $actorMuere = false;
        foreach ($enemigosRestantes as $enemigo) {
            $estimacion = $this->calculadoraDanio->mejorEstimacionContra($enemigo, $actor, $contexto->battle);
            if ($estimacion !== null && $estimacion->esperado >= $hpEfectivoActor) {
                $actorMuere = true;
                break;
            }
        }

        return $actorMuere ? -$this->pesos->puntosSupervivencia : $this->pesos->puntosSupervivencia;
    }

    /**
     * Evalúa si el actor queda en rango de KO de algún enemigo restante.
     */
    private function calcularRiesgo(ContextoDecisionIA $contexto): float
    {
        $actor = $contexto->actor;
        $hpActor = $actor->hpActual();

        foreach ($contexto->enemigos as $enemigo) {
            $estimacion = $this->calculadoraDanio->mejorEstimacionContra($enemigo, $actor, $contexto->battle);
            if ($estimacion !== null && $estimacion->esperado >= $hpActor) {
                return $this->pesos->puntosRiesgo;
            }
        }

        return 0.0;
    }
}
