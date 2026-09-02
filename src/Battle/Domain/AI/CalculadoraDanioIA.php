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

        // Daño efectivo al HP real, descontando la barrera física/especial
        $hpDamage = $this->calcularHpDamage($atacante, $defensor, $movimiento, $dano);

        $probabilidadKO = $hpDamage >= $defensor->hpActual() ? 1.0 : 0.0;

        return new EstimacionDanio(
            minimo: $dano,
            maximo: $dano,
            esperado: $dano,
            probabilidadKO: $probabilidadKO,
            hpDamage: $hpDamage,
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

    /**
     * Daño efectivo que recibe el HP real del defensor, replicando la lógica de
     * Combatiente::recibirDaño: una fracción (obtenerPorcentajeDanioDirecto) ignora
     * las barreras y va directa a HP; el resto se absorbe por la barrera física o
     * especial relevante (según categoría del movimiento) y solo el excedente se
     * desborda al HP.
     */
    public function calcularHpDamage(
        Combatiente $atacante,
        Combatiente $defensor,
        MovimientoBatalla $movimiento,
        float $danoBruto,
    ): float {
        $directPct = $atacante->obtenerPorcentajeDanioDirecto();

        $dañoDirecto = $danoBruto * $directPct;
        $dañoBarreras = $danoBruto - $dañoDirecto;

        $barreraRelevante = $movimiento->esEspecial()
            ? $defensor->defensaEspHpActual()
            : $defensor->defensaHpActual();

        // El excedente sobre la barrera se desborda al HP
        return $dañoDirecto + max(0.0, $dañoBarreras - $barreraRelevante);
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
