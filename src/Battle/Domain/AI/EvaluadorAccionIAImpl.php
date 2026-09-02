<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAccion;
use Src\Battle\Domain\AI\ValueObjects\EvaluacionAmenaza;
use Src\Battle\Domain\Combatiente;

/**
 * Evalúa cada acción candidata según KO, daño, reducción de amenaza, supervivencia, riesgo y lookahead.
 * Usa CalculadoraDanioIA (DRY) y PesosAmenaza (escalable).
 */
class EvaluadorAccionIAImpl implements EvaluadorAccionIA
{
    private SimuladorAccionIA $simulador;

    private RespuestaRival $respuestaRival;

    private EvaluadorPosicionIA $evaluadorPosicion;

    public function __construct(
        private readonly CalculadoraDanioIA $calculadoraDanio,
        private readonly PesosAmenaza $pesos,
    ) {
        $this->simulador = new SimuladorAccionIA($calculadoraDanio->cadenaDanio());
        $this->respuestaRival = new RespuestaRival($calculadoraDanio);
        $this->evaluadorPosicion = new EvaluadorPosicionIA($pesos);
    }

    public function evaluar(
        ContextoDecisionIA $contexto,
        array $amenazas,
        AccionBatalla $accion,
    ): EvaluacionAccion {
        $movimiento = $accion->move;
        $objetivo = $accion->defender;

        // --- KO Value ---
        $koValue = 0.0;
        $estimacionDanio = null;
        if (! $movimiento->esEstado()) {
            $estimacionDanio = $this->calculadoraDanio->estimar(
                $accion->attacker,
                $objetivo,
                $movimiento,
                $contexto->battle,
            );
            if ($estimacionDanio->probabilidadKO > 0) {
                $koValue = $this->pesos->puntosKO;
            }
        }

        // --- Damage Value ---
        // Se valora el daño EFECTIVO al HP real (hpDamage), no el daño bruto:
        // un golpe que se pierde absorbiendo una barrera física/especial no acerca al KO,
        // mientras que uno que daña el HP directamente (por barrera agotada o perforación)
        // sí. Dividimos entre el HP real para medir el progreso hacia el KO.
        $damageValue = 0.0;
        if ($estimacionDanio !== null) {
            $hpReal = $objetivo->hpActual();
            if ($hpReal > 0) {
                $damageValue = ($estimacionDanio->hpDamage / $hpReal) * $this->pesos->multiplicadorDanio;
            }
        }

        // --- Threat Reduction ---
        $threatReduction = $this->calcularReduccionAmenaza($amenazas, $objetivo, $koValue);

        // --- Survival Value ---
        $survivalValue = $this->calcularSupervivencia($contexto, $objetivo, $koValue);

        // --- Risk ---
        $risk = $this->calcularRiesgo($contexto);

        // --- Lookahead Score ---
        $lookaheadScore = 0.0;
        if ($this->debeAplicarLookahead($contexto)) {
            $lookaheadScore = $this->calcularLookahead($contexto, $accion, $koValue);
        }

        // --- Score ---
        $score = $koValue + $damageValue + $threatReduction + $survivalValue - $risk + $lookaheadScore;

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

    private function debeAplicarLookahead(ContextoDecisionIA $contexto): bool
    {
        return match ($contexto->dificultad) {
            NivelDificultad::PERFECTA => true,
            NivelDificultad::DIFICIL => true,
            NivelDificultad::NORMAL => false,
        };
    }

    /**
     * Simula la acción, genera la respuesta del rival, y evalúa la posición resultante.
     */
    private function calcularLookahead(
        ContextoDecisionIA $contexto,
        AccionBatalla $accion,
        float $koValue,
    ): float {
        $resultadoSimulacion = $this->simulador->simular($contexto->battle, $accion);

        // Si el objetivo muere, no hay respuesta rival
        if ($resultadoSimulacion->objetivoDerrotado) {
            return $this->pesos->puntosSupervivencia * 0.5;
        }

        $respuestas = $this->respuestaRival->generarRespuestas(
            $resultadoSimulacion->estadoSimulado,
            $accion->attacker,
            $contexto->equipoActor,
        );

        if ($respuestas === []) {
            return 0.0;
        }

        // Evaluar la peor respuesta del rival
        $peorPosicion = 0.0;
        $primera = true;

        foreach ($respuestas as $respuestaRival) {
            $resultadoRival = $this->simulador->simular(
                $resultadoSimulacion->estadoSimulado,
                $respuestaRival,
            );

            $posicionPostRival = $this->evaluadorPosicion->evaluar(
                $resultadoRival->estadoSimulado,
                $contexto->equipoActor,
            );

            if ($primera || $posicionPostRival < $peorPosicion) {
                $peorPosicion = $posicionPostRival;
                $primera = false;
            }
        }

        return $peorPosicion * 0.3;
    }

    /**
     * Si el objetivo muere, el score de amenaza de ese enemigo se suma como reducción.
     */
    private function calcularReduccionAmenaza(array $amenazas, Combatiente $objetivo, float $koValue): float
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
            $enemigosRestantes = array_values(array_filter(
                $contexto->enemigos,
                fn ($e) => $e->id() !== $objetivo->id()
            ));
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
