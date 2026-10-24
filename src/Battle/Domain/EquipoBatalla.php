<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Catalog\CatalogoHabilidades;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\Effects\Equipo\ColeccionEfectosEquipo;
use Src\Battle\Domain\Effects\Equipo\InterfazEfectoEquipo;
use Src\Battle\Domain\Effects\FabricaEfectos;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

class EquipoBatalla
{
    private CombatientesCollection $combatientes;

    private ColeccionEfectosEquipo $efectos;

    public function __construct(
        public readonly string $name,
    ) {
        $this->combatientes = new CombatientesCollection();
        $this->efectos = new ColeccionEfectosEquipo();
    }

    public function __clone(): void
    {
        $nuevaColeccion = new CombatientesCollection();
        foreach ($this->combatientes as $combatant) {
            $nuevaColeccion->add(clone $combatant);
        }
        $this->combatientes = $nuevaColeccion;
        $this->efectos = clone $this->efectos;
    }

    public function efectos(): ColeccionEfectosEquipo
    {
        return $this->efectos;
    }

    public function agregarEfecto(InterfazEfectoEquipo $efecto): void
    {
        $this->efectos->add($efecto);
    }

    public function multiplicadorDanoRecibido(AccionBatalla $accion): float
    {
        return $this->efectos->multiplicadorDanoRecibido($accion);
    }

    public function multiplicadorVelocidad(): float
    {
        $factor = 1.0;
        foreach ($this->efectos->all() as $efecto) {
            $factor *= $efecto->multiplicadorVelocidad();
        }

        return $factor;
    }

    public function consumirPantallas(float $dano, MovimientoBatalla $movimiento): void
    {
        $this->efectos->consumir($dano, $movimiento);
    }

    public function limpiarPantallas(): void
    {
        $this->efectos->limpiarPantallas();
    }

    public function onRoundStart(AgregadoBatalla $battle): void
    {
        $this->efectos->onRoundStart($this, $battle);
    }

    public function onRoundEnd(AgregadoBatalla $battle): void
    {
        $this->efectos->onRoundEnd($this, $battle);
    }

    public function bloqueaDanioIndirecto(): bool
    {
        foreach ($this->combatientes->vivos() as $combatiente) {
            if ($combatiente->bloqueaDanioIndirecto()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Colección tipada de combatientes.
     */
    public function combatientesCollection(): CombatientesCollection
    {
        return $this->combatientes;
    }

    public function combatants(): CombatientesCollection
    {
        return $this->combatientes;
    }

    public function agregarCombatiente(Combatiente $combatant, Posicion $posicion): void
    {
        $combatant->setPosicion($posicion);
        $this->combatientes->add($combatant);
    }

    /**
     * Retira un combatiente temporal (invocación) de la colección. No se usa
     * para Pokémon derrotados: esos permanecen en el equipo como K.O.
     */
    public function quitarCombatiente(Combatiente $combatiente): void
    {
        $this->combatientes->quitar($combatiente);
    }

    public function combatientesVivos(): CombatientesCollection
    {
        return $this->combatientes->vivos();
    }

    public function vanguardiaAlive(): CombatientesCollection
    {
        return $this->combatientes->vanguardia();
    }

    public function retaguardiaAlive(): CombatientesCollection
    {
        return $this->combatientes->retaguardia();
    }

    public function tieneVanguardiaViva(): bool
    {
        return $this->combatientes->vanguardia()->alMenosUnoVivo();
    }

    public function todosDebilitados(): bool
    {
        return ! $this->combatientes->alMenosUnoVivo();
    }

    public function lowestSpeed(): float
    {
        return $this->combatientes->menorVelocidadBase();
    }

    public static function fromData(DatosPokemonBatallaCollection $members, string $name, ?FabricaEfectos $fabricaEfectos = null): self
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
                ? new BattleStats($stats, $evs, $member->nivel, $member->ivs)
                : null;

            $pokemon = new PokemonEntity(
                stats: $stats,
                evs: $evs,
                moves: $member->moves,
                tiposCollection: $tipos,
                precomputedBattleStats: $precomputedBattleStats,
                peso: $member->peso,
            );

            $combatant = new Combatiente($pokemon, $member->posicion);
            $combatant->setId($member->id);
            $combatant->setNombre($member->nombre);
            $combatant->setIconName($member->iconName);
            $combatant->setSpeciesId($member->speciesId);
            $combatant->setFormSuffix($member->formSuffix);
            $combatant->setShiny($member->shiny);
            $combatant->setPostura($member->postura);

            // Procesar efectos/habilidades via Factory (una clave puede componer varios efectos)
            foreach ($member->effectKeys as $key) {
                $claves = CatalogoHabilidades::clavesDe($key);
                if ($claves->isEmpty()) {
                    $claves = new \Src\Shared\Collections\StringCollection([$key]);
                }

                foreach ($claves as $clave) {
                    foreach ($fabricaEfectos->crearEfectos($clave) as $effect) {
                        $combatant->effects()->add($effect);
                    }
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

            if ($member->esJefe) {
                $combatant->configurarJefe($member->fasesJefe, $member->puntosDebiles);
            }

            $team->agregarCombatiente($combatant, $member->posicion);
        }

        $team->normalizarCubridores();

        return $team;
    }

    /**
     * Mantiene coherentes los objetivos de Cubrir: cada cubridor vivo apunta
     * a otro aliado vivo. Si el objetivo murió, se reasigna automáticamente
     * sin pedir confirmación al jugador. Las posturas se configuran en Equipo.
     */
    public function normalizarCubridores(): void
    {
        $vivos = $this->combatientes->vivos()->all();

        foreach ($vivos as $combatiente) {
            if ($combatiente->postura() !== PosturaCombate::CUBRIR) {
                $combatiente->setObjetivoCubiertoId('');

                continue;
            }

            $objetivo = $this->findCombatantById($combatiente->objetivoCubiertoId());
            if ($objetivo !== null && $objetivo !== $combatiente && $objetivo->estaVivo()) {
                continue;
            }

            $nuevoObjetivo = null;
            foreach ($vivos as $aliado) {
                if ($aliado !== $combatiente) {
                    $nuevoObjetivo = $aliado;

                    break;
                }
            }

            $combatiente->setObjetivoCubiertoId($nuevoObjetivo?->id() ?? '');
        }
    }

    public function findCombatant(Combatiente $target): ?Combatiente
    {
        foreach ($this->combatientes as $c) {
            if ($c->id() === $target->id()) {
                return $c;
            }
        }

        return null;
    }

    public function findCombatantById(string $id): ?Combatiente
    {
        foreach ($this->combatientes as $c) {
            if ($c->id() === $id) {
                return $c;
            }
        }

        return null;
    }
}
