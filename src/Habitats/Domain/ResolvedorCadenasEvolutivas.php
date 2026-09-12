<?php

declare(strict_types=1);

namespace Src\Habitats\Domain;

use Closure;
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
     *
     * @param  list<array{id: int, name: string, species_id: int}>  $pokemon
     * @param  list<array{evolved_species_id: int, evolves_from_species_id: int|null}>  $evolutions
     * @return list<array{id: int, name: string, icon: string, stage: int, species_id: int}>
     */
    public function getFamilyMembersByChain(array $pokemon, array $evolutions): array
    {
        if ($pokemon === []) {
            return [];
        }

        $ids = array_column($pokemon, 'id');
        $idsSet = array_fill_keys($ids, true);

        /** @var array<int, int|null> $evolvesFrom */
        $evolvesFrom = [];
        foreach ($evolutions as $row) {
            if (! isset($idsSet[$row['evolved_species_id']])) {
                continue;
            }
            $evolvesFrom[$row['evolved_species_id']] = $row['evolves_from_species_id'];
        }

        // Base = el miembro cuyo evolves_from es null o no está en la familia.
        $baseId = null;
        foreach ($pokemon as $p) {
            $from = $evolvesFrom[$p['id']] ?? null;
            if ($from === null || ! isset($idsSet[$from])) {
                $baseId = $p['id'];
                break;
            }
        }
        if ($baseId === null) {
            $baseId = $pokemon[0]['id'];
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
        foreach ($pokemon as $p) {
            $stages[$p['id']] ??= 3;
        }

        return array_map(fn (array $p): array => [
            'id' => $p['id'],
            'name' => $p['name'],
            'icon' => ($this->iconPath)($p['id']),
            'stage' => $stages[$p['id']] ?? 3,
            'species_id' => $p['species_id'],
        ], $pokemon);
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    public function totalStages(array $members): int
    {
        return max(array_column($members, 'stage'));
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
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     * @param  callable(array{id: int, name: string, icon: string, stage: int, species_id: int}): DTOPokemonFamilia  $entryBuilder
     * @return array{0: ?DTOPokemonFamilia, 1: ColeccionPokemonFamilia}
     */
    public function splitFamilyMembers(array $members, callable $entryBuilder): array
    {
        if ($members === []) {
            return [null, new ColeccionPokemonFamilia()];
        }

        $base = $entryBuilder($members[0]);
        $evolutions = new ColeccionPokemonFamilia(array_map($entryBuilder, array_slice($members, 1)));

        return [$base, $evolutions];
    }
}
