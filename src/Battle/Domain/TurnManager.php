<?php

namespace Src\Battle\Domain;

class TurnManager
{
    public int $round = 0;

    /** @var Combatant[] */
    private array $teamA;
    private array $teamB;

    public function __construct(
        public readonly BattleTeam $team1,
        public readonly BattleTeam $team2,
    ) {
        $this->teamA = $team1->combatants;
        $this->teamB = $team2->combatants;
    }

    public function allCombatants(): array
    {
        return array_merge($this->teamA, $this->teamB);
    }

    public function aliveCombatants(): array
    {
        return array_values(array_filter(
            $this->allCombatants(),
            fn(Combatant $c) => $c->isAlive()
        ));
    }

    public function lowestSpeedAmongAlive(): float
    {
        $alive = $this->aliveCombatants();

        if (empty($alive)) {
            return 0;
        }

        return min(array_map(
            fn(Combatant $c) => $c->pokemon->battleStats->speed,
            $alive
        ));
    }

    public function startNewRound(): void
    {
        $this->round++;

        foreach ($this->allCombatants() as $combatant) {
            if ($combatant->isAlive()) {
                $combatant->addSpeed();
                $combatant->timesActedThisRound = 0;
            }
        }
    }

    public function getNextActor(): ?Combatant
    {
        $lowest = $this->lowestSpeedAmongAlive();
        if ($lowest <= 0) {
            return null;
        }

        $alive = $this->aliveCombatants();

        if (empty($alive)) {
            return null;
        }

        $maxSpeed = -1;
        $selected = null;

        foreach ($alive as $combatant) {
            if ($combatant->accumulatedSpeed > $maxSpeed) {
                $maxSpeed = $combatant->accumulatedSpeed;
                $selected = $combatant;
            }
        }

        if ($selected === null) {
            return null;
        }

        if ($selected->accumulatedSpeed <= 0) {
            return null;
        }

        return $selected;
    }

    public function consumeAction(Combatant $actor): void
    {
        $lowest = $this->lowestSpeedAmongAlive();
        $actor->reducirSpeed($lowest <= 0 ? 1 : $lowest);
        $actor->timesActedThisRound++;
    }

    public function hayAlgunoConAccionPendiente(): bool
    {
        $lowest = $this->lowestSpeedAmongAlive();
        if ($lowest <= 0) {
            return false;
        }

        foreach ($this->aliveCombatants() as $c) {
            if ($c->accumulatedSpeed > 0) {
                return true;
            }
        }

        return false;
    }

    public function bothTeamsAlive(): bool
    {
        return !$this->team1->allFainted() && !$this->team2->allFainted();
    }
}
