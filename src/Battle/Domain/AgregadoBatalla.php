<?php

namespace Src\Battle\Domain;

use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Shared\Tipos\TipoPokemon;

class AgregadoBatalla
{
    public GestorTurnos $turnManager;
    public CadenaDanio $damageChain;
    public SujetoBatalla $subject;
    public array $log = [];
    public ?array $pendingAction = null;
    public string $weather = 'none';

    public function __construct(
        public readonly EquipoBatalla $team1,
        public readonly EquipoBatalla $team2,
    ) {
        $this->turnManager = new GestorTurnos($team1, $team2);
        $this->damageChain = new CadenaDanio();
        $this->subject = new SujetoBatalla();
    }

    /**
     * Dispara onBattleStart en todos los efectos de todos los combatientes.
     */
    public function triggerBattleStartEffects(): void
    {
        foreach ($this->turnManager->allCombatants() as $c) {
            $c->effects->triggerBattleStart($c, $this);
        }
    }

    /**
     * Dispara onRoundStart en todos los efectos de combatientes vivos.
     */
    public function triggerRoundStartEffects(): void
    {
        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects->triggerRoundStart($c, $this);
        }
    }

    /**
     * Dispara onRoundEnd en todos los efectos de combatientes vivos
     * y aplica daño por estado (quemadura/envenenamiento).
     */
    public function triggerRoundEndEffects(): void
    {
        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects->triggerRoundEnd($c, $this);

            // Daño por estado al final de la ronda
            $dañoStatus = $c->aplicarDañoStatus();
            if ($dañoStatus > 0) {
                $label = Combatiente::STATUS_LABELS[$c->estado] ?? $c->estado;
                $this->log("[{$c->nombre}] sufre {$dañoStatus} de daño por {$label}");
                if (!$c->estaVivo()) {
                    $this->log("¡[{$c->nombre}] se ha debilitado por {$label}!");
                }
            }

            // Daño por clima (granizo / tormenta arena)
            $dañoClima = $this->calcularDañoClima($c);
            if ($dañoClima > 0) {
                $c->hpActual = max(0, $c->hpActual - $dañoClima);
                $climaLabel = match ($this->weather) {
                    'granizo' => 'granizo',
                    'tormenta_arena' => 'tormenta de arena',
                    default => 'clima',
                };
                $this->log("[{$c->nombre}] sufre {$dañoClima} de daño por {$climaLabel}");
                if (!$c->estaVivo()) {
                    $this->log("¡[{$c->nombre}] se ha debilitado por {$climaLabel}!");
                }
            }
        }
    }

    /**
     * Calcula el daño por clima para un combatiente.
     * Granizo: daño 6.25% a los que no son HIELO.
     * Tormenta arena: daño 6.25% a los que no son ROCA/TIERRA/ACERO.
     */
    private function calcularDañoClima(Combatiente $c): float
    {
        if (!$c->estaVivo()) {
            return 0;
        }

        if ($this->weather === 'granizo') {
            foreach ($c->pokemon->tiposCollection as $tipo) {
                if ($tipo === TipoPokemon::HIELO) {
                    return 0;
                }
            }
            return max(1, $c->pokemon->battleStats->hp * 0.0625);
        }

        if ($this->weather === 'tormenta_arena') {
            foreach ($c->pokemon->tiposCollection as $tipo) {
                if (in_array($tipo, [TipoPokemon::ROCA, TipoPokemon::TIERRA, TipoPokemon::ACERO], true)) {
                    return 0;
                }
            }
            return max(1, $c->pokemon->battleStats->hp * 0.0625);
        }

        return 0;
    }

    public function ejecutarBatalla(): array
    {
        $this->log('¡Comienza la batalla!');

        while ($this->turnManager->bothTeamsAlive()) {
            $this->turnManager->startNewRound();
            $this->log("--- Ronda {$this->turnManager->round} ---");

            while ($this->turnManager->hayAlgunoConAccionPendiente() && $this->turnManager->bothTeamsAlive()) {
                $actor = $this->turnManager->getNextActor();

                if ($actor === null) {
                    break;
                }

                $this->ejecutarAccion($actor);
                $this->turnManager->consumeAction($actor);
            }

            // Efectos de fin de ronda + daño por estado
            $this->triggerRoundEndEffects();

            foreach ($this->turnManager->combatientesVivos() as $c) {
                $this->subject->notifyEndTurn($c);
            }
        }

        $winner = !$this->team1->todosDebilitados() ? $this->team1->name : $this->team2->name;
        $this->log("¡{$winner} gana la batalla!");

        return $this->log;
    }

    private function ejecutarAccion(Combatiente $actor): void
    {
        $objetivo = $this->elegirObjetivo($actor);
        if ($objetivo === null) {
            return;
        }

        $movimiento = $this->elegirMejorMovimiento($actor, $objetivo);
        if ($movimiento === null) {
            return;
        }

        $enemigo = $actor->estaEnVanguardia() ? $this->team2 : $this->team1;
        $defenderTeamHasVanguard = $enemigo->tieneVanguardiaViva();

        $servicio = new ServicioEjecucionBatalla($this->damageChain);
        $resultado = $servicio->calcularYAplicarDaño(
            $actor, $objetivo, $movimiento,
            $this->weather, $defenderTeamHasVanguard,
        );

        $daño = $resultado['daño'];
        $servicio->aplicarEstado($objetivo, $movimiento);
        $servicio->aplicarStatChanges($actor, $objetivo, $movimiento);

        $this->log(
            "{$actor->velocidadAcumulada}vel [{$actor->hpActual}hp] "
            . "ataca a [{$objetivo->hpActual}hp] "
            . "con {$movimiento->nombre} -> {$daño} de daño"
        );

        $this->subject->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $this);
        $actor->dispararDanioInfligido($objetivo, $daño, $this);

        if (!$objetivo->estaVivo()) {
            $this->log("¡[{$objetivo->nombre}] se ha debilitado!");
            $this->subject->notifyFainted($objetivo);
        }
    }

    /**
     * Elige un objetivo enemigo para el actor dado (público para IA/Livewire).
     */
    public function elegirObjetivoPara(Combatiente $actor): ?Combatiente
    {
        return $this->elegirObjetivo($actor);
    }

    private function elegirObjetivo(Combatiente $actor): ?Combatiente
    {
        // Determinar equipo enemigo según a qué equipo pertenece el actor
        $enemigo = $this->team1->findCombatant($actor) !== null
            ? $this->team2
            : $this->team1;

        if ($actor->estaEnVanguardia()) {
            $vanguardiaEnemiga = $enemigo->vanguardiaAlive();
            if (!empty($vanguardiaEnemiga)) {
                return $vanguardiaEnemiga[array_rand($vanguardiaEnemiga)];
            }

            $retaguardiaEnemiga = $enemigo->retaguardiaAlive();
            if (!empty($retaguardiaEnemiga)) {
                return $retaguardiaEnemiga[array_rand($retaguardiaEnemiga)];
            }
        }

        if ($actor->estaEnRetaguardia()) {
            $todosEnemigos = $enemigo->combatientesVivos();
            if (!empty($todosEnemigos)) {
                return $todosEnemigos[array_rand($todosEnemigos)];
            }
        }

        // Fallback: cualquier enemigo vivo
        $todosEnemigos = $enemigo->combatientesVivos();
        return !empty($todosEnemigos) ? $todosEnemigos[array_rand($todosEnemigos)] : null;
    }

    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        if (empty($attacker->pokemon->moves)) {
            return new MovimientoBatalla('Placaje', 40, \Src\Shared\Tipos\TipoPokemon::NORMAL, 'fisico');
        }

        $best = null;
        $bestScore = -1;

        foreach ($attacker->pokemon->moves as $move) {
            if ($move instanceof MovimientoBatalla) {
                $efectividad = $move->tipo->effectiveness($defender->pokemon);
                $score = $efectividad * $move->potencia;

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $move;
                }
            }
        }

        return $best;
    }

    private function log(string $message): void
    {
        $this->log[] = $message;
    }
}
