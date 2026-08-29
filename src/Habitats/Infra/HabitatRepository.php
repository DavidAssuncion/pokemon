<?php

declare(strict_types=1);

namespace Src\Habitats\Infra;

use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use App\Models\Province;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Domain\HabitatEntity;
use Src\Habitats\Domain\HabitatsCollection;
use Src\Habitats\Domain\ProvinceEntity;
use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliaSinHabitat;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;
use Src\Habitats\Presentation\DTOHabitatDetalle;
use Src\Habitats\Presentation\DTOPokemonNivelActualizado;

/**
 * Repositorio Eloquent de hábitats.
 *
 * El campo `icon` de los JSON servidos apunta a WebP optimizado:
 * `/images/iconos_webp/{id}.webp`. Los PNG originales quedan en
 * `/images/iconos/{id}.png` como fuente/fallback.
 */
class HabitatRepository implements HabitatRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection
    {
        $provinces = Province::with('habitats')->get()->sortBy('id');

        $items = [];
        foreach ($provinces as $province) {
            $habitats = new HabitatsCollection();
            foreach ($province->habitats as $habitat) {
                $habitats->add(new HabitatEntity(
                    id: $habitat->id,
                    name: $habitat->name,
                    provinceId: $habitat->province_id,
                ));
            }

            $items[] = new ProvinceEntity(
                id: $province->id,
                name: $province->name,
                habitats: $habitats,
            );
        }

        return new ProvinciasCollection($items);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getPokemonsByHabitat(int $habitatId): array
    {
        $habitat = Habitat::find($habitatId);
        if ($habitat === null) {
            return [];
        }

        return $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name'])
            ->get()
            ->map(fn ($pokemon) => [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
            ])
            ->toArray();
    }

    public function getHabitatDetail(int $habitatId): DTOHabitatDetalle
    {
        $habitat = Habitat::find($habitatId);
        if ($habitat === null) {
            return new DTOHabitatDetalle(0, '', '', [1 => [], 2 => [], 3 => []]);
        }

        $levels = [1 => [], 2 => [], 3 => []];

        $habitatPokemon = $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name'])
            ->get()
            ->sortBy('pokemon.id')
            ->map(fn ($pokemon) => [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
                'level' => intval($pokemon->pivot->level ?? 2),
                'icon' => $this->iconPath($pokemon->id),
            ]);

        foreach ($habitatPokemon as $pokemon) {
            $level = $pokemon['level'];
            if (! in_array($level, [1, 2, 3], true)) {
                $level = 2;
            }

            $levels[$level][] = [
                'id' => $pokemon['id'],
                'name' => $pokemon['name'],
                'icon' => $pokemon['icon'],
            ];
        }

        return new DTOHabitatDetalle(
            id: $habitat->id,
            name: $habitat->name,
            image: "/habitats-img/{$habitat->id}.webp",
            levels: $levels,
            min_lvl_1: $this->minLvlNullable($habitat->getAttribute('min_lvl_1')),
            min_lvl_2: $this->minLvlNullable($habitat->getAttribute('min_lvl_2')),
            min_lvl_3: $this->minLvlNullable($habitat->getAttribute('min_lvl_3')),
        );
    }

    public function getFamiliesByHabitat(int $habitatId): DTOFamiliasDisponibles
    {
        $habitat = Habitat::find($habitatId);
        if ($habitat === null) {
            return new DTOFamiliasDisponibles();
        }

        $chainIds = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->where('pokemon_habitat.habitat_id', $habitatId)
            ->whereNotNull('pokemon.evolution_chain_id')
            ->distinct()
            ->pluck('pokemon.evolution_chain_id')
            ->values()
            ->toArray();

        $chainIds = $this->sortChainIdsByMinSpeciesId($chainIds);

        $result = new DTOFamiliasDisponibles();

        foreach ($chainIds as $chainId) {
            $members = $this->getFamilyMembersByChain($chainId);
            if ($members === []) {
                continue;
            }

            $family = $this->buildAvailableFamilyFromChain($chainId, $members);
            if ($family !== null) {
                $result->add($family);
            }
        }

        return $result;
    }

    public function getUnassignedFamilies(): DTOFamiliasSinHabitat
    {
        $assignedChainIds = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->whereNotNull('pokemon.evolution_chain_id')
            ->distinct()
            ->pluck('pokemon.evolution_chain_id')
            ->values()
            ->toArray();

        $unassignedChains = Pokemon::whereNotNull('evolution_chain_id')
            ->whereNotIn('evolution_chain_id', $assignedChainIds)
            ->distinct()
            ->pluck('evolution_chain_id')
            ->values()
            ->toArray();

        $unassignedChains = $this->sortChainIdsByMinSpeciesId($unassignedChains);

        $result = new DTOFamiliasSinHabitat();

        foreach ($unassignedChains as $chainId) {
            $members = $this->getFamilyMembersByChain($chainId);
            if ($members === []) {
                continue;
            }

            $family = $this->buildUnassignedFamilyFromChain($chainId, $members);
            if ($family !== null) {
                $result->add($family);
            }
        }

        return $result;
    }

    public function assignFamily(int $habitatId, int $evolutionChainId): DTOFamiliaDisponible
    {
        $this->assertHabitatExists($habitatId);

        $members = $this->getFamilyMembersByChain($evolutionChainId);
        $this->assertFamilyMembersExist($evolutionChainId, $members);

        $totalStages = $this->totalStages($members);

        DB::transaction(function () use ($habitatId, $members, $totalStages) {
            $records = array_map(fn (array $member) => [
                'pokemon_id' => $member['id'],
                'habitat_id' => $habitatId,
                'level' => $this->levelForStage($member['stage'], $totalStages),
            ], $members);

            DB::table('pokemon_habitat')
                ->upsert($records, ['pokemon_id', 'habitat_id'], ['level']);
        });

        // Reconstruye la familia completa con los niveles REALES por miembro (incluye ramificaciones:
        // levelForStage aplica el mismo reparto que el upsert anterior).
        $family = $this->buildAvailableFamilyFromChain($evolutionChainId, $members);

        return $family ?? throw new \LogicException("La cadena evolutiva {$evolutionChainId} no tiene pokémon base");
    }

    public function removeFamily(int $habitatId, int $evolutionChainId): DTOFamiliaEliminada
    {
        $this->assertHabitatExists($habitatId);

        $members = $this->getFamilyMembersByChain($evolutionChainId);
        $this->assertFamilyMembersExist($evolutionChainId, $members);

        $pokemonIds = array_map(fn (array $member) => $member['id'], $members);

        $removedCount = 0;

        DB::transaction(function () use ($habitatId, $pokemonIds, &$removedCount) {
            $removedCount = DB::table('pokemon_habitat')
                ->where('habitat_id', $habitatId)
                ->whereIn('pokemon_id', $pokemonIds)
                ->delete();
        });

        return new DTOFamiliaEliminada($habitatId, $evolutionChainId, $removedCount);
    }

    public function movePokemonToLevel(int $habitatId, int $pokemonId, int $level): DTOPokemonNivelActualizado
    {
        if ($level < 1 || $level > 3) {
            throw new \InvalidArgumentException("El nivel {$level} no es válido. Debe estar entre 1 y 3");
        }

        $this->assertHabitatExists($habitatId);

        $pokemon = Pokemon::find($pokemonId);
        if ($pokemon === null) {
            throw new \InvalidArgumentException("El pokémon {$pokemonId} no existe");
        }

        $row = DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new \InvalidArgumentException("El pokémon {$pokemonId} no está asignado al hábitat {$habitatId}");
        }

        $previousLevel = (int) $row->level;

        DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->update(['level' => $level]);

        return new DTOPokemonNivelActualizado(
            habitatId: $habitatId,
            pokemonId: $pokemonId,
            previousLevel: $previousLevel,
            newLevel: $level,
        );
    }

    /**
     * Resuelve todos los miembros de la cadena con su etapa evolutiva,
     * empezando por la base (evolves_from_species_id null) y haciendo BFS.
     * Los miembros se ordenan por species_id asc (el "primer integrante" de la
     * familia, criterio de negocio) con desempate por id.
     *
     * @return array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>
     */
    private function getFamilyMembersByChain(int $chainId): array
    {
        // Miembros de la familia (por pokemon.evolution_chain_id), ordenados por species_id
        $pokemon = Pokemon::where('evolution_chain_id', $chainId)
            ->get(['id', 'name', 'species_id'])
            ->sortBy([
                ['species_id', 'asc'],
                ['id', 'asc'],
            ]);

        if ($pokemon->isEmpty()) {
            return [];
        }

        // Mapa evolutivo: evolved_species_id => evolves_from_species_id (solo de este chain, filtrando por los ids de la familia)
        $ids = $pokemon->pluck('id')->map(fn ($id) => (int) $id)->all();
        $evolutionRows = PokemonEvolution::whereIn('evolved_species_id', $ids)
            ->get(['evolved_species_id', 'evolves_from_species_id']);

        $evolvesFrom = [];
        foreach ($evolutionRows as $row) {
            $evolvesFrom[(int) $row['evolved_species_id']] = $row['evolves_from_species_id'] !== null ? (int) $row['evolves_from_species_id'] : null;
        }

        // Base = el miembro cuyo evolves_from es null o no está en la familia
        $baseId = null;
        foreach ($pokemon as $p) {
            $from = $evolvesFrom[(int) $p['id']] ?? null;
            if ($from === null || ! in_array($from, $ids, true)) {
                $baseId = (int) $p['id'];
                break;
            }
        }
        if ($baseId === null) {
            $baseId = (int) $pokemon->first()['id'];
        }

        // BFS: base stage 1, hijos directos stage 2, resto stage 3
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
            $stages[(int) $p['id']] ??= 3;
        }

        return $pokemon->map(fn ($p) => [
            'id' => (int) $p['id'],
            'name' => $p['name'],
            'icon' => $this->iconPath((int) $p['id']),
            'stage' => $stages[(int) $p['id']] ?? 3,
            'species_id' => (int) $p['species_id'],
        ])->values()->toArray();
    }

    /**
     * Ordena los ids de cadena evolutiva por el species_id mínimo de sus miembros
     * (el "primer integrante" de cada familia, criterio de negocio).
     *
     * @param  list<int>  $chainIds
     * @return list<int>
     */
    private function sortChainIdsByMinSpeciesId(array $chainIds): array
    {
        if ($chainIds === []) {
            return [];
        }

        /** @var array<int, int> $minSpeciesByChain */
        $minSpeciesByChain = Pokemon::whereIn('evolution_chain_id', $chainIds)
            ->get(['evolution_chain_id', 'species_id'])
            ->groupBy('evolution_chain_id')
            ->map(fn ($members): int => (int) $members->min('species_id'))
            ->all();

        usort($chainIds, fn (int $a, int $b): int => ($minSpeciesByChain[$a] ?? PHP_INT_MAX) <=> ($minSpeciesByChain[$b] ?? PHP_INT_MAX));

        return $chainIds;
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    private function buildAvailableFamilyFromChain(int $chainId, array $members): ?DTOFamiliaDisponible
    {
        $totalStages = $this->totalStages($members);

        [$base, $evolutions] = $this->splitFamilyMembers($members, fn (array $member): array => [
            'id' => $member['id'],
            'name' => $member['name'],
            'icon' => $this->iconPath($member['id']),
            'level' => $this->levelForStage($member['stage'], $totalStages),
        ]);

        if ($base === null) {
            return null;
        }

        return new DTOFamiliaDisponible(
            evolutionChainId: $chainId,
            base: $base,
            evolutions: $evolutions,
            types: $this->getChainTypes($members),
        );
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    private function buildUnassignedFamilyFromChain(int $chainId, array $members): ?DTOFamiliaSinHabitat
    {
        [$base, $evolutions] = $this->splitFamilyMembers($members, fn (array $member): array => [
            'id' => $member['id'],
            'name' => $member['name'],
            'icon' => $this->iconPath($member['id']),
        ]);

        if ($base === null) {
            return null;
        }

        return new DTOFamiliaSinHabitat(
            evolutionChainId: $chainId,
            base: $base,
            evolutions: $evolutions,
            types: $this->getChainTypes($members),
        );
    }

    /**
     * Divide los miembros de una familia entre el "primer integrante" (menor
     * species_id, ya ordenado por getFamilyMembersByChain) y el resto de
     * evoluciones, construyendo la entrada de cada uno con el builder recibido.
     *
     * @template TEntry of array
     *
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     * @param  callable(array{id: int, name: string, icon: string, stage: int, species_id: int}): TEntry  $entryBuilder
     * @return array{0: ?TEntry, 1: array<int, TEntry>}
     */
    private function splitFamilyMembers(array $members, callable $entryBuilder): array
    {
        if ($members === []) {
            return [null, []];
        }

        $base = $entryBuilder($members[0]);
        $evolutions = array_map($entryBuilder, array_slice($members, 1));

        return [$base, $evolutions];
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     * @return array<int, array{id: int, name: string}>
     */
    private function getChainTypes(array $members): array
    {
        $ids = array_map(fn (array $member) => $member['id'], $members);

        $types = PokemonType::whereIn('pokemon_id', $ids)
            ->get()
            ->pluck('type')
            ->map(fn (TipoEnum $type) => ['id' => $type->value, 'name' => $type->label()])
            ->unique('id')
            ->sortBy('id')
            ->values()
            ->toArray();

        return $types;
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    private function totalStages(array $members): int
    {
        return max(array_column($members, 'stage'));
    }

    private function levelForStage(int $stage, int $totalStages): int
    {
        if ($totalStages === 1) {
            return 2;
        }

        return min($stage, 3);
    }

    private function assertHabitatExists(int $habitatId): void
    {
        if (Habitat::find($habitatId) === null) {
            throw new \InvalidArgumentException("El hábitat {$habitatId} no existe");
        }
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    private function assertFamilyMembersExist(int $evolutionChainId, array $members): void
    {
        if ($members === []) {
            throw new \InvalidArgumentException("La cadena evolutiva {$evolutionChainId} no existe o no tiene pokémon");
        }
    }

    private function iconPath(int $pokemonId): string
    {
        return "/images/iconos_webp/{$pokemonId}.webp";
    }

    private function minLvlNullable(mixed $valor): ?int
    {
        return $valor !== null ? (int) $valor : null;
    }
}
