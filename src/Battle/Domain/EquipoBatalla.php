<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

class EquipoBatalla
{
    /** @var Combatiente[] */
    private array $combatants = [];

    public function __construct(
        public readonly string $name,
    ) {
    }

    public function __clone(): void
    {
        foreach ($this->combatants as $key => $combatant) {
            $this->combatants[$key] = clone $combatant;
        }
    }

    /**
     * @return Combatiente[]
     */
    public function combatants(): array
    {
        return $this->combatants;
    }

    public function agregarCombatiente(Combatiente $combatant, Posicion $posicion): void
    {
        $combatant->setPosicion($posicion);
        $this->combatants[] = $combatant;
    }

    public function combatientesVivos(): array
    {
        return array_values(array_filter($this->combatants, fn (Combatiente $c) => $c->estaVivo()));
    }

    public function vanguardiaAlive(): array
    {
        return array_values(array_filter(
            $this->combatants,
            fn (Combatiente $c) => $c->estaVivo() && $c->estaEnVanguardia()
        ));
    }

    public function retaguardiaAlive(): array
    {
        return array_values(array_filter(
            $this->combatants,
            fn (Combatiente $c) => $c->estaVivo() && $c->estaEnRetaguardia()
        ));
    }

    public function tieneVanguardiaViva(): bool
    {
        return ! empty($this->vanguardiaAlive());
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
            fn (Combatiente $c) => $c->pokemon()->battleStats()->speed,
            $alive
        ));
    }

    /**
     * @param  DatosPokemonBatalla[]  $members
     */
    public static function fromData(array $members, string $name, ?FabricaEfectos $fabricaEfectos = null): self
    {
        $fabricaEfectos ??= new FabricaEfectos();
        $team = new self($name);

        foreach ($members as $member) {
            $stats = new StatsValue(
                hp: $member->hp,
                attack: $member->atk,
                defense: $member->def,
                spAtk: $member->spAtk,
                spDef: $member->spDef,
                speed: $member->speed,
            );

            $evs = $member->evs ?? new StatsValue(0, 0, 0, 0, 0, 0);
            $tipos = new TiposCollection($member->tipos);

            $precomputedBattleStats = $member->nivel !== null
                ? new BattleStats($stats, $evs, $member->nivel)
                : null;

            $pokemon = new PokemonEntity(
                stats: $stats,
                evs: $evs,
                moves: $member->moves,
                tiposCollection: $tipos,
                precomputedBattleStats: $precomputedBattleStats,
            );

            $combatant = new Combatiente($pokemon, $member->posicion);
            $combatant->setId($member->id);
            $combatant->setNombre($member->nombre);
            $combatant->setIconName($member->iconName);
            $combatant->setSpeciesId($member->speciesId);
            $combatant->setFormSuffix($member->formSuffix);
            $combatant->setShiny($member->shiny);

            // Procesar efectos/habilidades via Factory
            foreach ($member->effectKeys as $key) {
                $effect = $fabricaEfectos->crearEfecto($key);
                if ($effect !== null) {
                    $combatant->effects()->add($effect);
                }
            }

            // Procesar objeto equipado via Factory
            if ($member->item !== null) {
                $combatant->setItem($member->item);
                $itemEffect = $fabricaEfectos->crearItem($member->item);
                if ($itemEffect !== null) {
                    $combatant->effects()->add($itemEffect);
                }
            }

            $team->agregarCombatiente($combatant, $member->posicion);
        }

        return $team;
    }

    public function findCombatant(Combatiente $target): ?Combatiente
    {
        foreach ($this->combatants as $c) {
            if ($c->id() === $target->id()) {
                return $c;
            }
        }

        return null;
    }

    public function findCombatantById(string $id): ?Combatiente
    {
        foreach ($this->combatants as $c) {
            if ($c->id() === $id) {
                return $c;
            }
        }

        return null;
    }
}
