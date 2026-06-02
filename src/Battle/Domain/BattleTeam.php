<?php

namespace Src\Battle\Domain;

use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

class BattleTeam
{
    /** @var Combatant[] */
    public array $combatants = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function addCombatant(Combatant $combatant, Position $position): void
    {
        $combatant->position = $position;
        $this->combatants[] = $combatant;
    }

    public function aliveCombatants(): array
    {
        return array_values(array_filter($this->combatants, fn(Combatant $c) => $c->isAlive()));
    }

    public function vanguardiaAlive(): array
    {
        return array_values(array_filter(
            $this->combatants,
            fn(Combatant $c) => $c->isAlive() && $c->estaEnVanguardia()
        ));
    }

    public function retaguardiaAlive(): array
    {
        return array_values(array_filter(
            $this->combatants,
            fn(Combatant $c) => $c->isAlive() && $c->estaEnRetaguardia()
        ));
    }

    public function tieneVanguardiaViva(): bool
    {
        return !empty($this->vanguardiaAlive());
    }

    public function allFainted(): bool
    {
        return empty($this->aliveCombatants());
    }

    public function lowestSpeed(): float
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

    /** @param BattlePokemonData[] $members */
    public static function fromData(array $members, string $name): self
    {
        $team = new self($name);

        foreach ($members as $member) {
            $stats = new StatsValue(
                hp: $member->hp, attack: $member->atk, defense: $member->def,
                spAtk: $member->spAtk, spDef: $member->spDef, speed: $member->speed,
            );

            $evs = new StatsValue(0, 0, 0, 0, 0, 0);
            $tipos = new TiposCollection($member->tipos);

            $pokemon = new PokemonEntity(
                stats: $stats,
                evs: $evs,
                moves: $member->moves,
                tiposCollection: $tipos,
            );

            $combatant = new Combatant($pokemon, $member->posicion);
            $combatant->id = $member->id;
            $combatant->nombre = $member->nombre;
            $combatant->iconName = $member->iconName;
            $combatant->shiny = $member->shiny;

            $team->addCombatant($combatant, $member->posicion);
        }

        return $team;
    }



    public function findCombatant(Combatant $target): ?Combatant
    {
        foreach ($this->combatants as $c) {
            if ($c->id === $target->id) {
                return $c;
            }
        }
        return null;
    }

    public function findCombatantById(string $id): ?Combatant
    {
        foreach ($this->combatants as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }
        return null;
    }
}
