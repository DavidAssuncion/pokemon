<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\ValueObjects\EstimacionDanio;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Servicio compartido de estimación de daño para la IA.
 * Reutiliza CadenaDanio para no duplicar la fórmula de daño.
 * Centraliza la lógica usada por AnalizadorAmenazas y EvaluadorAccionIA (DRY).
 */
class CalculadoraDanioIA
{
    public function __construct(
        private readonly CadenaDanio $cadenaDanio,
    ) {
    }

    public function cadenaDanio(): CadenaDanio
    {
        return $this->cadenaDanio;
    }

    /**
     * Estima el daño de un movimiento de un atacante contra un defensor.
     */
    public function estimar(
        Combatiente $atacante,
        Combatiente $defensor,
        MovimientoBatalla $movimiento,
        AgregadoBatalla $batalla,
    ): EstimacionDanio {
        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: $movimiento,
            fromPosition: $atacante->posicion(),
            defenderTeamHasVanguard: $this->obtenerDefensorTieneVanguardia($atacante, $defensor, $batalla),
            weather: $batalla->weather(),
        );

        // Semilla fija para modo determinista (sin variabilidad de críticos)
        $seed = mt_rand();
        mt_srand(42);
        $dano = $this->cadenaDanio->calculate($accion);
        mt_srand($seed);

        $hpEfectivo = $this->calcularHPEfectivo($defensor, $movimiento);

        return new EstimacionDanio(
            minimo: $dano,
            maximo: $dano,
            esperado: $dano,
            probabilidadKO: $dano >= $hpEfectivo ? 1.0 : 0.0,
        );
    }

    /**
     * Mejor estimación de daño que un atacante puede infligir a un defensor.
     * Itera todos los movimientos y retorna la de mayor daño esperado.
     */
    public function mejorEstimacionContra(
        Combatiente $atacante,
        Combatiente $defensor,
        AgregadoBatalla $batalla,
    ): ?EstimacionDanio {
        $mejorEstimacion = null;
        $mejorDano = -1.0;

        foreach ($atacante->pokemon()->moves() as $movimiento) {
            if (! $movimiento instanceof MovimientoBatalla) {
                continue;
            }

            $estimacion = $this->estimar($atacante, $defensor, $movimiento, $batalla);
            if ($estimacion->esperado > $mejorDano) {
                $mejorDano = $estimacion->esperado;
                $mejorEstimacion = $estimacion;
            }
        }

        return $mejorEstimacion;
    }

    /**
     * HP efectivo = HP actual + barrera relevante según categoría del movimiento.
     */
    public function calcularHPEfectivo(Combatiente $defensor, MovimientoBatalla $movimiento): float
    {
        $hp = $defensor->hpActual();

        if ($movimiento->esFisico()) {
            return $hp + $defensor->defensaHpActual();
        }

        if ($movimiento->esEspecial()) {
            return $hp + $defensor->defensaEspHpActual();
        }

        // Movimientos de estado: solo HP
        return $hp;
    }

    private function obtenerDefensorTieneVanguardia(
        Combatiente $atacante,
        Combatiente $defensor,
        AgregadoBatalla $batalla,
    ): bool {
        $equipoEnemigo = $batalla->team1->findCombatant($atacante) !== null
            ? $batalla->team2
            : $batalla->team1;

        return $equipoEnemigo->tieneVanguardiaViva();
    }
}
