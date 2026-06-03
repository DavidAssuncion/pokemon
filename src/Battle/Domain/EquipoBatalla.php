<?php

namespace Src\Battle\Domain;

use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

class EquipoBatalla
{
    /** @var Combatiente[] */
    public array $combatants = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function agregarCombatiente(Combatiente $combatant, Posicion $posicion): void
    {
        $combatant->posicion = $posicion;
        $this->combatants[] = $combatant;
    }

    public function combatientesVivos(): array
    {
        return array_values(array_filter($this->combatants, fn(Combatiente $c) => $c->estaVivo()));
    }

    public function vanguardiaAlive(): array
    {
        return array_values(array_filter(
            $this->combatants,
            fn(Combatiente $c) => $c->estaVivo() && $c->estaEnVanguardia()
        ));
    }

    public function retaguardiaAlive(): array
    {
        return array_values(array_filter(
            $this->combatants,
            fn(Combatiente $c) => $c->estaVivo() && $c->estaEnRetaguardia()
        ));
    }

    public function tieneVanguardiaViva(): bool
    {
        return !empty($this->vanguardiaAlive());
    }

    public function todosDebilitados(): bool
    {
        return empty($this->combatientesVivos());
    }

    public function lowestSpeed(): float
    {
        $alive = $this->combatientesVivos();
        if (empty($alive)) {
            return 0;
        }

        return min(array_map(
            fn(Combatiente $c) => $c->pokemon->battleStats->speed,
            $alive
        ));
    }

    /** @param DatosPokemonBatalla[] $members */
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

            $combatant = new Combatiente($pokemon, $member->posicion);
            $combatant->id = $member->id;
            $combatant->nombre = $member->nombre;
            $combatant->iconName = $member->iconName;
            $combatant->shiny = $member->shiny;

            // Procesar efectos/habilidades via Factory
            foreach ($member->effectKeys as $key) {
                $effect = FabricaEfectos::crearEfecto($key);
                if ($effect !== null) {
                    $combatant->effects->add($effect);
                }
            }

            // Procesar objeto equipado via Factory
            if ($member->item !== null) {
                $combatant->item = $member->item;
                $itemEffect = FabricaEfectos::crearItem($member->item);
                if ($itemEffect !== null) {
                    $combatant->effects->add($itemEffect);
                }
            }

            $team->agregarCombatiente($combatant, $member->posicion);
        }

        return $team;
    }



    public function findCombatant(Combatiente $target): ?Combatiente
    {
        foreach ($this->combatants as $c) {
            if ($c->id === $target->id) {
                return $c;
            }
        }
        return null;
    }

    public function findCombatantById(string $id): ?Combatiente
    {
        foreach ($this->combatants as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }
        return null;
    }
}
