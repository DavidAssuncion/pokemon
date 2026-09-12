<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CombatientesCollection;

class GestorTurnos
{
    private int $round = 0;

    private CombatientesCollection $teamA;

    private CombatientesCollection $teamB;

    public function __construct(
        public readonly EquipoBatalla $team1,
        public readonly EquipoBatalla $team2,
    ) {
        $this->teamA = $team1->combatientesCollection();
        $this->teamB = $team2->combatientesCollection();
    }

    /**
     * Todos los combatientes de ambos equipos (vivos y muertos).
     */
    public function allCombatants(): CombatientesCollection
    {
        $todos = new CombatientesCollection();
        foreach ($this->teamA as $c) {
            $todos->add($c);
        }
        foreach ($this->teamB as $c) {
            $todos->add($c);
        }

        return $todos;
    }

    /**
     * Solo los combatientes vivos de ambos equipos.
     */
    public function combatientesVivos(): CombatientesCollection
    {
        return $this->allCombatants()->vivos();
    }

    /**
     * Menor velocidad efectiva entre combatientes vivos.
     * 0 si no hay vivos.
     */
    public function menorVelocidadEntreVivos(): float
    {
        return $this->combatientesVivos()->menorVelocidadEfectiva();
    }

    public function startNewRound(): void
    {
        $this->round++;

        foreach ($this->allCombatants() as $combatant) {
            if ($combatant->estaVivo()) {
                $combatant->agregarVelocidad();
                $combatant->setVecesActuadoEstaRonda(0);
            }
        }
    }

    public function getNextActor(): ?Combatiente
    {
        $lowest = $this->menorVelocidadEntreVivos();
        if ($lowest <= 0) {
            return null;
        }

        $alive = $this->combatientesVivos();

        if ($alive->isEmpty()) {
            return null;
        }

        $selected = $alive->mayorVelocidadActual();

        if ($selected === null) {
            return null;
        }

        if ($selected->velocidadAcumulada() <= 0) {
            return null;
        }

        return $selected;
    }

    public function consumeAction(Combatiente $actor): void
    {
        $lowest = $this->menorVelocidadEntreVivos();
        $actor->reducirVelocidad($lowest <= 0 ? 1 : $lowest);
        $actor->setVecesActuadoEstaRonda($actor->vecesActuadoEstaRonda() + 1);
    }

    public function hayAlgunoConAccionPendiente(): bool
    {
        $lowest = $this->menorVelocidadEntreVivos();
        if ($lowest <= 0) {
            return false;
        }

        foreach ($this->combatientesVivos() as $c) {
            if ($c->velocidadAcumulada() > 0) {
                return true;
            }
        }

        return false;
    }

    public function bothTeamsAlive(): bool
    {
        return ! $this->team1->todosDebilitados() && ! $this->team2->todosDebilitados();
    }

    public function round(): int
    {
        return $this->round;
    }
}
