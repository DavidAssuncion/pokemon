<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\AI\NivelDificultad;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;

/**
 * Ejecuta la acción de un actor: selección de IA, servicio de daño, estado,
 * consumo, memoria, log, observadores y consecuencias. Sin estado.
 */
final class EjecutorAccion
{
    public function ejecutar(
        AgregadoBatalla $batalla,
        Combatiente $actor,
        SelectorAccionIA $selector,
        NivelDificultad $dificultad,
    ): void {
        $objetivo = $selector->elegirObjetivoPara($batalla, $actor);
        if ($objetivo === null) {
            $actor->romperCombo();

            return;
        }

        $movimiento = $this->elegirMovimiento($selector, $batalla, $actor, $dificultad);
        if (! $actor->movimientoDisponible($movimiento)) {
            $actor->romperCombo();

            return;
        }

        $accion = $this->construirAccion($batalla, $actor, $objetivo, $movimiento);
        $servicio = new ServicioEjecucionBatalla($batalla->damageChain());
        $resultado = $servicio->calcularYAplicarDano($accion, $batalla);

        $servicio->aplicarEstadoYStatChanges($accion, $batalla, $resultado->objetivos);
        $actor->consumirMovimiento($movimiento);

        // Alimentar la memoria con la acción observada
        $selector->registrarAccionEnemiga($accion, $resultado->dano, $batalla->turnManager()->round());

        $this->registrarLog($batalla, $actor, $objetivo, $movimiento, $resultado->dano);
        $this->notificarDano($batalla, $actor, $objetivo, $resultado->dano);

        // Cubre objetivos secundarios, cubridores, vínculos y muertes por
        // reacciones. La notificación es idempotente por combatiente.
        $this->procesarConsecuencias($batalla, $actor, $objetivo, $selector);
    }

    private function elegirMovimiento(
        SelectorAccionIA $selector,
        AgregadoBatalla $batalla,
        Combatiente $actor,
        NivelDificultad $dificultad,
    ): MovimientoBatalla {
        return $selector->elegirAccion($batalla, $actor, $dificultad)->accion->move;
    }

    private function construirAccion(
        AgregadoBatalla $batalla,
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
    ): AccionBatalla {
        $enemigo = $actor->estaEnVanguardia() ? $batalla->team2 : $batalla->team1;

        return new AccionBatalla(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            defenderTeamHasVanguard: $enemigo->tieneVanguardiaViva(),
            weather: $batalla->weather(),
            terreno: $batalla->terreno(),
        );
    }

    private function registrarLog(
        AgregadoBatalla $batalla,
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        float $daño,
    ): void {
        $batalla->agregarLog(
            "{$actor->velocidadEfectiva()}vel [{$actor->hpActual()}hp] "
            ."ataca a [{$objetivo->hpActual()}hp] "
            ."con {$movimiento->nombre} -> {$daño} de daño"
        );
    }

    private function notificarDano(
        AgregadoBatalla $batalla,
        Combatiente $actor,
        Combatiente $objetivo,
        float $daño,
    ): void {
        $batalla->subject()->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $batalla);
        $actor->dispararDanioInfligido($objetivo, $daño, $batalla);
    }

    private function procesarConsecuencias(
        AgregadoBatalla $batalla,
        Combatiente $actor,
        Combatiente $objetivo,
        SelectorAccionIA $selector,
    ): void {
        if (! $objetivo->estaVivo()) {
            $batalla->agregarLog("¡{$objetivo->nombre()} se ha debilitado!");
            $selector->memoria()->registrarKO(
                $batalla->turnManager()->round(),
                $actor->id(),
                $objetivo->id(),
            );
        }

        $nuevosDebilitados = $batalla->procesarDebilitamientos();
        if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
            $actor->otorgarAccionExtra();
        }

        if (! $actor->estaVivo()) {
            $batalla->agregarLog("¡{$actor->nombre()} se ha debilitado por el efecto!");
        }
    }
}
