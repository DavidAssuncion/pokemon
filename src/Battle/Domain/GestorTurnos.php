<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

class GestorTurnos
{
    private int $round = 0;

    public function round(): int
    {
        return $this->round;
    }

    /** @var Combatiente[] */
    private array $teamA;

    /** @var Combatiente[] */
    private array $teamB;

    public function __construct(
        public readonly EquipoBatalla $team1,
        public readonly EquipoBatalla $team2,
    ) {
        $this->teamA = $team1->combatants();
        $this->teamB = $team2->combatants();
    }

    public function allCombatants(): array
    {
        return array_merge($this->teamA, $this->teamB);
    }

    public function combatientesVivos(): array
    {
        return array_values(array_filter(
            $this->allCombatants(),
            fn (Combatiente $c) => $c->estaVivo()
        ));
    }

    public function menorVelocidadEntreVivos(): float
    {
        $alive = $this->combatientesVivos();

        if (empty($alive)) {
            return 0;
        }

        return min(array_map(
            fn (Combatiente $c) => $c->obtenerStatEfectivo('speed'),
            $alive
        ));
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

        if (empty($alive)) {
            return null;
        }

        $maxSpeed = -1;
        $selected = null;

        foreach ($alive as $combatant) {
            if ($combatant->velocidadAcumulada() > $maxSpeed) {
                $maxSpeed = $combatant->velocidadAcumulada();
                $selected = $combatant;
            }
        }

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
}
