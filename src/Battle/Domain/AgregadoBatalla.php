<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Battle\Presentation\DTOAccionBatalla;

class AgregadoBatalla
{
    private GestorTurnos $turnManager;

    private CadenaDanio $damageChain;

    private SujetoBatalla $subject;

    /** @var string[] */
    private array $log = [];

    private ?DTOAccionBatalla $pendingAction = null;

    private TipoClima $weather = TipoClima::NONE;

    private ?CalculadorDañoClima $calculadorClima = null;

    private ?SelectorAccionIA $selectorAccion = null;

    public function __construct(
        public readonly EquipoBatalla $team1,
        public readonly EquipoBatalla $team2,
    ) {
        $this->turnManager = new GestorTurnos($team1, $team2);
        $this->damageChain = new CadenaDanio();
        $this->subject = new SujetoBatalla();
    }

    // ─── Getters ──────────────────────────────────────────────

    public function turnManager(): GestorTurnos
    {
        return $this->turnManager;
    }

    public function damageChain(): CadenaDanio
    {
        return $this->damageChain;
    }

    public function subject(): SujetoBatalla
    {
        return $this->subject;
    }

    /**
     * @return string[]
     */
    public function log(): array
    {
        return $this->log;
    }

    public function pendingAction(): ?DTOAccionBatalla
    {
        return $this->pendingAction;
    }

    public function setPendingAction(?DTOAccionBatalla $pendingAction): void
    {
        $this->pendingAction = $pendingAction;
    }

    public function weather(): TipoClima
    {
        return $this->weather;
    }

    public function setWeather(TipoClima $weather): void
    {
        $this->weather = $weather;
    }

    private function getCalculadorClima(): CalculadorDañoClima
    {
        return $this->calculadorClima ??= new CalculadorDañoClima();
    }

    private function getSelectorAccion(): SelectorAccionIA
    {
        return $this->selectorAccion ??= new SelectorAccionIA();
    }

    // ─── Log ──────────────────────────────────────────────────

    public function agregarLog(string $entry): void
    {
        $this->log[] = $entry;
    }

    public function limpiarLog(): void
    {
        $this->log = [];
    }

    /**
     * Dispara onBattleStart en todos los efectos de todos los combatientes.
     */
    public function triggerBattleStartEffects(): void
    {
        foreach ($this->turnManager->allCombatants() as $c) {
            $c->effects()->triggerBattleStart($c, $this);
        }
    }

    /**
     * Dispara onRoundStart en todos los efectos de combatientes vivos.
     */
    public function triggerRoundStartEffects(): void
    {
        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects()->triggerRoundStart($c, $this);
        }
    }

    /**
     * Dispara onRoundEnd en todos los efectos de combatientes vivos
     * y aplica daño por estado (quemadura/envenenamiento).
     */
    public function triggerRoundEndEffects(): void
    {
        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects()->triggerRoundEnd($c, $this);

            // Daño por estado al final de la ronda
            $dañoStatus = $c->aplicarDañoStatus();
            if ($dañoStatus > 0) {
                $label = $c->estado()->label();
                $this->agregarLog("{$c->nombre()} sufre {$dañoStatus} de daño por {$label}");
                if (! $c->estaVivo()) {
                    $this->agregarLog("¡{$c->nombre()} se ha debilitado por {$label}!");
                }
            }

            // Daño por clima (granizo / tormenta arena)
            $dañoClima = $this->getCalculadorClima()->calcular($c, $this->weather);
            if ($dañoClima > 0) {
                $c->setHpActual(max(0, $c->hpActual() - $dañoClima));
                $climaLabel = match ($this->weather) {
                    TipoClima::GRANIZO => 'granizo',
                    TipoClima::TORMENTA_ARENA => 'tormenta de arena',
                    default => 'clima',
                };
                $this->agregarLog("{$c->nombre()} sufre {$dañoClima} de daño por {$climaLabel}");
                if (! $c->estaVivo()) {
                    $this->agregarLog("¡{$c->nombre()} se ha debilitado por {$climaLabel}!");
                }
            }
        }
    }

    public function ejecutarBatalla(): array
    {
        $this->agregarLog('¡Comienza la batalla!');

        while ($this->turnManager->bothTeamsAlive()) {
            $this->turnManager->startNewRound();
            $this->agregarLog("--- Ronda {$this->turnManager->round()} ---");

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

        $winner = ! $this->team1->todosDebilitados() ? $this->team1->name : $this->team2->name;
        $this->agregarLog("¡{$winner} gana la batalla!");

        return $this->log;
    }

    private function ejecutarAccion(Combatiente $actor): void
    {
        $objetivo = $this->getSelectorAccion()->elegirObjetivoPara($this, $actor);
        if ($objetivo === null) {
            return;
        }

        $movimiento = $this->elegirMejorMovimiento($actor, $objetivo);
        if ($movimiento === null) {
            return;
        }

        $enemigo = $actor->estaEnVanguardia() ? $this->team2 : $this->team1;
        $defenderTeamHasVanguard = $enemigo->tieneVanguardiaViva();

        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            fromPosition: $actor->posicion(),
            defenderTeamHasVanguard: $defenderTeamHasVanguard,
            weather: $this->weather,
        );

        $servicio = new ServicioEjecucionBatalla($this->damageChain);
        $resultado = $servicio->calcularYAplicarDano($accion);

        $daño = $resultado->dano;
        $servicio->aplicarEstado($objetivo, $movimiento);
        $servicio->aplicarStatChanges($actor, $objetivo, $movimiento);

        $this->agregarLog(
            "{$actor->velocidadAcumulada()}vel {$actor->hpActual()}hp] "
            ."ataca a {$objetivo->hpActual()}hp] "
            ."con {$movimiento->nombre} -> {$daño} de daño"
        );

        $this->subject->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $this);
        $actor->dispararDanioInfligido($objetivo, $daño, $this);

        if (! $objetivo->estaVivo()) {
            $this->agregarLog("¡{$objetivo->nombre()} se ha debilitado!");
            $this->subject->notifyFainted($objetivo);
        }
    }

    /**
     * Elige un objetivo enemigo para el actor dado (público para IA/Livewire).
     */
    public function elegirObjetivoPara(Combatiente $actor): ?Combatiente
    {
        return $this->getSelectorAccion()->elegirObjetivoPara($this, $actor);
    }

    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        return $this->getSelectorAccion()->elegirMejorMovimiento($attacker, $defender);
    }
}
