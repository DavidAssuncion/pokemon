<?php

declare(strict_types=1);

namespace Src\Habitats\Domain;

use Closure;
use Src\Habitats\Domain\Collections\EvolucionCadenaCollection;
use Src\Habitats\Domain\Collections\MiembrosCadenaCollection;
use Src\Habitats\Domain\Collections\PokemonCadenaCollection;
use Src\Habitats\Domain\DataTransferObjects\MiembroCadena;
use Src\Habitats\Domain\DataTransferObjects\PokemonCadenaBase;
use Src\Habitats\Domain\DataTransferObjects\RepartoFamilia;
use Src\Habitats\Presentation\ColeccionPokemonFamilia;
use Src\Habitats\Presentation\DTOPokemonFamilia;

/**
 * Resuelve cadenas evolutivas: miembros y etapas por BFS, orden por species_id
 * mínimo y reparto de niveles.
 *
 * Vive en Domain porque es lógica de dominio pura: recibe los datos ya cargados
 * por el repositorio (sin Eloquent) y solo procesa BFS/orden/reparto. El icono
 * de cada pokémon se construye con el callable inyectado (responsabilidad de la
 * capa Infra, que posee la ruta).
 */
final class ResolvedorCadenasEvolutivas
{
    private readonly Closure $iconPath;

    /**
     * @param  callable(int): string  $iconPath  Devuelve la ruta del icono WebP de un pokémon
     */
    public function __construct(callable $iconPath)
    {
        $this->iconPath = Closure::fromCallable($iconPath);
    }

    /**
     * Resuelve todos los miembros de la cadena con su etapa evolutiva,
     * empezando por la base (evolves_from_species_id null) y haciendo BFS.
     *
     * Se espera `$pokemon` ya ordenado por species_id asc (desempate por id): el
     * "primer integrante" de la familia es el de menor species_id (criterio de
     * negocio). Las evoluciones deben venir filtradas al chain en curso; la base
     * es el miembro cuyo evolves_from es null o no pertenece a la familia.
     */
    public function getFamilyMembersByChain(
        PokemonCadenaCollection $pokemon,
        EvolucionCadenaCollection $evolutions,
    ): MiembrosCadenaCollection {
        if ($pokemon->isEmpty()) {
            return new MiembrosCadenaCollection();
        }

        $idsSet = array_fill_keys(
            $pokemon->map(fn (PokemonCadenaBase $p): int => $p->id),
            true,
        );

        /** @var array<int, int|null> $evolvesFrom */
        $evolvesFrom = [];
        foreach ($evolutions as $row) {
            if (! isset($idsSet[$row->evolvedSpeciesId])) {
                continue;
            }
            $evolvesFrom[$row->evolvedSpeciesId] = $row->evolvesFromSpeciesId;
        }

        // Base = el miembro cuyo evolves_from es null o no está en la familia.
        $baseId = null;
        foreach ($pokemon as $p) {
            $from = $evolvesFrom[$p->id] ?? null;
            if ($from === null || ! isset($idsSet[$from])) {
                $baseId = $p->id;
                break;
            }
        }
        if ($baseId === null) {
            $baseId = $pokemon->first()?->id;
        }

        // BFS: base stage 1, hijos directos stage 2, resto stage 3.
        $stages = [];
        $stage = 1;
        $current = [$baseId];
        while ($current !== [] && $stage <= 3) {
            $next = [];
            foreach ($current as $pid) {
                $stages[$pid] = $stage;
                foreach ($evolvesFrom as $evolvedId => $fromId) {
                    if ($fromId === $pid && ! isset($stages[$evolvedId])) {
                        $next[] = $evolvedId;
                    }
                }
            }
            $current = $next;
            $stage++;
        }

        $miembros = new MiembrosCadenaCollection();
        foreach ($pokemon as $p) {
            $miembros->add(new MiembroCadena(
                id: $p->id,
                nombre: $p->nombre,
                icono: ($this->iconPath)($p->id),
                stage: $stages[$p->id] ?? 3,
                speciesId: $p->speciesId,
            ));
        }

        return $miembros;
    }

    public function totalStages(MiembrosCadenaCollection $members): int
    {
        return max([1, ...$members->map(fn (MiembroCadena $m): int => $m->stage)]);
    }

    public function levelForStage(int $stage, int $totalStages): int
    {
        if ($totalStages === 1) {
            return 2;
        }

        return min($stage, 3);
    }

    /**
     * Divide los miembros de una familia entre el "primer integrante" (menor
     * species_id, ya ordenado por getFamilyMembersByChain) y el resto de
     * evoluciones, construyendo la entrada de cada uno con el builder recibido.
     *
     * @param  callable(MiembroCadena): DTOPokemonFamilia  $entryBuilder
     */
    public function splitFamilyMembers(MiembrosCadenaCollection $members, callable $entryBuilder): RepartoFamilia
    {
        $primero = $members->primero();

        if ($primero === null) {
            return new RepartoFamilia(null, new ColeccionPokemonFamilia());
        }

        $resto = $members->all();
        array_shift($resto);

        return new RepartoFamilia(
            $entryBuilder($primero),
            new ColeccionPokemonFamilia(array_map($entryBuilder, $resto)),
        );
    }
}
