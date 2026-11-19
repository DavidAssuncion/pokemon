<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\CalculadorDañoClima;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Exceptions\LimiteRondasAlcanzado;
use Src\Battle\Domain\Observer\ObservadorEjecucionBatalla;
use Src\Battle\Domain\ValueObjects\BattleLog;

/**
 * Secuencia de ronda, efectos de inicio/fin y observadores. Sin estado: cada
 * llamada recibe el agregado y los colaboradores que necesita.
 */
final class MotorRondas
{
    /**
     * @param  callable(Combatiente): void  $ejecutarAccion
     */
    public function ejecutarBatalla(
        AgregadoBatalla $batalla,
        callable $ejecutarAccion,
        ?int $maxRondas = null,
        ?ObservadorEjecucionBatalla $observador = null,
    ): BattleLog {
        $this->validarRondas($maxRondas);
        $batalla->agregarLog('¡Comienza la batalla!');

        while ($batalla->turnManager()->bothTeamsAlive()) {
            $this->comprobarLimite($batalla, $maxRondas);
            $this->iniciarRonda($batalla);
            $this->ejecutarTurnosDeRonda($batalla, $ejecutarAccion, $observador);
            $this->finalizarRonda($batalla, $observador);
        }

        $batalla->agregarLog("¡{$this->nombreGanador($batalla)} gana la batalla!");

        return $batalla->log();
    }

    public function triggerBattleStartEffects(AgregadoBatalla $batalla): void
    {
        foreach ($batalla->turnManager()->allCombatants() as $c) {
            $c->effects()->triggerBattleStart($c, $batalla);
        }
    }

    public function triggerRoundStartEffects(AgregadoBatalla $batalla): void
    {
        // Si el portador del gas era una invocación retirada al final de la
        // ronda anterior, las habilidades deben volver antes de actuar.
        $batalla->sincronizarGasReactivo();

        $batalla->team1->onRoundStart($batalla);
        $batalla->team2->onRoundStart($batalla);

        foreach ($batalla->turnManager()->combatientesVivos() as $c) {
            $c->effects()->triggerRoundStart($c, $batalla);
        }

        // Trampas de equipo o efectos de inicio pueden dejar KO pendientes.
        $this->procesarDebilitamientos($batalla);
    }

    /**
     * @param  callable(): CalculadorDañoClima  $obtenerCalculador
     */
    public function triggerRoundEndEffects(AgregadoBatalla $batalla, callable $obtenerCalculador): void
    {
        $batalla->sincronizarGasReactivo();

        $this->aplicarEfectosPorCombatiente($batalla, $obtenerCalculador);

        $batalla->team1->onRoundEnd($batalla);
        $batalla->team2->onRoundEnd($batalla);
        $batalla->procesarDanioDiferido();
        $this->procesarDebilitamientos($batalla);

        // Cierre final: Comealmas y efectos similares observan todas las
        // muertes de la ronda, incluidas las de estado/clima/Detonación.
        foreach ($batalla->turnManager()->combatientesVivos() as $c) {
            $c->effects()->triggerRoundEndFinal($c, $batalla);
        }

        (new GestorInvocaciones())->retirarExpiradas($batalla);
        (new GestorClimaTerreno())->avanzarTurnos($batalla);
    }

    public function procesarDebilitamientos(AgregadoBatalla $batalla): int
    {
        $total = 0;

        do {
            $nuevos = 0;
            foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
                if ($combatiente->notificarDebilitamiento($batalla)) {
                    $nuevos++;
                    $total++;
                }
            }
        } while ($nuevos > 0);

        return $total;
    }

    private function validarRondas(?int $maxRondas): void
    {
        if ($maxRondas !== null && $maxRondas < 1) {
            throw new \InvalidArgumentException('maxRondas debe ser mayor o igual que 1');
        }
    }

    private function comprobarLimite(AgregadoBatalla $batalla, ?int $maxRondas): void
    {
        if ($maxRondas !== null && $batalla->turnManager()->round() >= $maxRondas) {
            throw new LimiteRondasAlcanzado($maxRondas, $batalla->turnManager()->round());
        }
    }

    private function iniciarRonda(AgregadoBatalla $batalla): void
    {
        $batalla->turnManager()->startNewRound();
        $this->triggerRoundStartEffects($batalla);
        $batalla->agregarLog("--- Ronda {$batalla->turnManager()->round()} ---");
    }

    /**
     * @param  callable(Combatiente): void  $ejecutarAccion
     */
    private function ejecutarTurnosDeRonda(
        AgregadoBatalla $batalla,
        callable $ejecutarAccion,
        ?ObservadorEjecucionBatalla $observador,
    ): void {
        $turnos = $batalla->turnManager();

        while ($turnos->hayAlgunoConAccionPendiente() && $turnos->bothTeamsAlive()) {
            $actor = $turnos->getNextActor();

            if ($actor === null) {
                break;
            }

            $actor->effects()->dispararInicioTurno($actor, $batalla);
            $puedeActuar = $actor->puedeActuar();
            if (! $puedeActuar->esPermitida()) {
                $actor->effects()->dispararTurnoPerdido($actor, $batalla);
                $batalla->agregarLog("{$actor->nombre()} no puede actuar: {$puedeActuar->motivo()}");
                $turnos->consumeAction($actor);
                $observador?->alFinalizarTurno($turnos->round(), $actor, $batalla);

                continue;
            }

            $ejecutarAccion($actor);
            $actor->effects()->dispararFinTurno($actor, $batalla);
            $this->procesarDebilitamientos($batalla);
            $turnos->consumeAction($actor);
            $observador?->alFinalizarTurno($turnos->round(), $actor, $batalla);
        }
    }

    private function finalizarRonda(AgregadoBatalla $batalla, ?ObservadorEjecucionBatalla $observador): void
    {
        $batalla->triggerRoundEndEffects();

        foreach ($batalla->turnManager()->combatientesVivos() as $c) {
            $batalla->subject()->notifyEndTurn($c);
        }

        $observador?->alFinalizarRonda($batalla->turnManager()->round(), $batalla);
    }

    private function nombreGanador(AgregadoBatalla $batalla): string
    {
        return ! $batalla->team1->todosDebilitados() ? $batalla->team1->name : $batalla->team2->name;
    }

    /**
     * @param  callable(): CalculadorDañoClima  $obtenerCalculador
     */
    private function aplicarEfectosPorCombatiente(AgregadoBatalla $batalla, callable $obtenerCalculador): void
    {
        foreach ($batalla->turnManager()->combatientesVivos() as $c) {
            $c->effects()->triggerRoundEnd($c, $batalla);

            if (! $this->equipoProtegido($batalla, $c)) {
                $this->aplicarDanioIndirecto($batalla, $c, $obtenerCalculador);
            }

            // Los multiplicadores temporales deben avanzar aunque Muro Mágico
            // bloquee el daño indirecto.
            $c->avanzarMultiplicadoresTemporales();
        }
    }

    /**
     * @param  callable(): CalculadorDañoClima  $obtenerCalculador
     */
    private function aplicarDanioIndirecto(
        AgregadoBatalla $batalla,
        Combatiente $c,
        callable $obtenerCalculador,
    ): void {
        (new ProcesadorCondicionesRonda())->procesar($batalla, $c);

        // Daño por estado al final de la ronda
        $dañoStatus = $c->aplicarDañoStatus();
        if ($dañoStatus > 0) {
            $label = $c->estado()->label();
            $batalla->agregarLog("{$c->nombre()} sufre {$dañoStatus} de daño por {$label}");
            if (! $c->estaVivo()) {
                $batalla->agregarLog("¡{$c->nombre()} se ha debilitado por {$label}!");
            }
        }

        // Daño por clima (granizo / tormenta arena): golpea primero la barrera física
        $calculadorClima = $obtenerCalculador();
        $dañoClima = $calculadorClima->calcular($c, $batalla->weather());
        if ($dañoClima > 0) {
            $c->recibirDaño($dañoClima, false);
            $climaLabel = $batalla->weather()->label();
            $batalla->agregarLog("{$c->nombre()} sufre {$dañoClima} de daño por {$climaLabel}");
            if (! $c->estaVivo()) {
                $batalla->agregarLog("¡{$c->nombre()} se ha debilitado por {$climaLabel}!");
            }
        }
    }

    private function equipoProtegido(AgregadoBatalla $batalla, Combatiente $combatiente): bool
    {
        $equipo = $batalla->team1->findCombatant($combatiente) !== null ? $batalla->team1 : $batalla->team2;

        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado->bloqueaDanioIndirecto()) {
                return true;
            }
        }

        return false;
    }
}
