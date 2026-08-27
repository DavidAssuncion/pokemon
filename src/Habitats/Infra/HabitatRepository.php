<?php

declare(strict_types=1);

namespace Src\Habitats\Infra;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\Province;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Domain\HabitatEntity;
use Src\Habitats\Domain\HabitatsCollection;
use Src\Habitats\Domain\ProvinceEntity;
use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;
use Src\Habitats\Presentation\DTOFamiliaAsignada;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliaSinHabitat;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;
use Src\Habitats\Presentation\DTOHabitatDetalle;

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
                'icon' => "/images/iconos/{$pokemon->id}.png",
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

        $result = new DTOFamiliasDisponibles();

        foreach ($chainIds as $chainId) {
            $members = $this->getFamilyMembersByChain((int) $chainId);
            if ($members === []) {
                continue;
            }

            $family = $this->buildAvailableFamilyFromChain((int) $chainId, $members);
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

        $result = new DTOFamiliasSinHabitat();

        foreach ($unassignedChains as $chainId) {
            $members = $this->getFamilyMembersByChain((int) $chainId);
            if ($members === []) {
                continue;
            }

            $family = $this->buildUnassignedFamilyFromChain((int) $chainId, $members);
            if ($family !== null) {
                $result->add($family);
            }
        }

        return $result;
    }

    public function assignFamily(int $habitatId, int $evolutionChainId): DTOFamiliaAsignada
    {
        if (Habitat::find($habitatId) === null) {
            throw new \InvalidArgumentException("El hábitat {$habitatId} no existe");
        }

        $members = $this->getFamilyMembersByChain($evolutionChainId);
        if ($members === []) {
            throw new \InvalidArgumentException("La cadena evolutiva {$evolutionChainId} no existe o no tiene pokémon");
        }

        $totalStages = $this->totalStages($members);

        $assignedCount = 0;

        DB::transaction(function () use ($habitatId, $members, $totalStages, &$assignedCount) {
            $records = array_map(fn (array $member) => [
                'pokemon_id' => $member['id'],
                'habitat_id' => $habitatId,
                'level' => $this->levelForStage($member['stage'], $totalStages),
            ], $members);

            $assignedCount = DB::table('pokemon_habitat')
                ->upsert($records, ['pokemon_id', 'habitat_id'], ['level']);
        });

        Cache::forget("habitats.family_chain.{$evolutionChainId}");

        return new DTOFamiliaAsignada($habitatId, $evolutionChainId, $assignedCount);
    }

    public function removeFamily(int $habitatId, int $evolutionChainId): DTOFamiliaEliminada
    {
        if (Habitat::find($habitatId) === null) {
            throw new \InvalidArgumentException("El hábitat {$habitatId} no existe");
        }

        $members = $this->getFamilyMembersByChain($evolutionChainId);
        if ($members === []) {
            throw new \InvalidArgumentException("La cadena evolutiva {$evolutionChainId} no existe o no tiene pokémon");
        }

        $pokemonIds = array_map(fn (array $member) => $member['id'], $members);

        $removedCount = 0;

        DB::transaction(function () use ($habitatId, $pokemonIds, &$removedCount) {
            $removedCount = DB::table('pokemon_habitat')
                ->where('habitat_id', $habitatId)
                ->whereIn('pokemon_id', $pokemonIds)
                ->delete();
        });

        Cache::forget("habitats.family_chain.{$evolutionChainId}");

        return new DTOFamiliaEliminada($habitatId, $evolutionChainId, $removedCount);
    }

    /**
     * @return array<int, int>
     */
    public function getFamilyPokemonsByChain(int $evolutionChainId): array
    {
        return Cache::remember("habitats.family_chain.{$evolutionChainId}", 3600, function () use ($evolutionChainId) {
            return Pokemon::where('evolution_chain_id', $evolutionChainId)
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Resuelve todos los miembros de la cadena con su etapa evolutiva,
     * empezando por la base (evolves_from_species_id null) y haciendo BFS.
     *
     * @return array<int, array{id: int, name: string, icon: string, stage: int}>
     */
    private function getFamilyMembersByChain(int $chainId): array
    {
        // Miembros de la familia (por pokemon.evolution_chain_id)
        $pokemon = Pokemon::where('evolution_chain_id', $chainId)->get(['id', 'name']);

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
        while (! empty($current) && $stage <= 3) {
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
            'icon' => "/images/iconos/{$p['id']}.png",
            'stage' => $stages[(int) $p['id']] ?? 3,
        ])->values()->toArray();
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int}>  $members
     */
    private function buildAvailableFamilyFromChain(int $chainId, array $members): ?DTOFamiliaDisponible
    {
        $totalStages = $this->totalStages($members);

        $base = null;
        $evolutions = [];
        foreach ($members as $member) {
            $entry = [
                'id' => $member['id'],
                'name' => $member['name'],
                'icon' => "/images/iconos/{$member['id']}.png",
                'level' => $this->levelForStage($member['stage'], $totalStages),
            ];

            if ($member['stage'] === 1) {
                $base = $entry;
            } else {
                $evolutions[] = $entry;
            }
        }

        if ($base === null) {
            return null;
        }

        return new DTOFamiliaDisponible(
            evolutionChainId: $chainId,
            base: $base,
            evolutions: $evolutions,
        );
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int}>  $members
     */
    private function buildUnassignedFamilyFromChain(int $chainId, array $members): ?DTOFamiliaSinHabitat
    {
        $base = null;
        $evolutions = [];
        foreach ($members as $member) {
            $entry = [
                'id' => $member['id'],
                'name' => $member['name'],
                'icon' => "/images/iconos/{$member['id']}.png",
            ];

            if ($member['stage'] === 1) {
                $base = $entry;
            } else {
                $evolutions[] = $entry;
            }
        }

        if ($base === null) {
            return null;
        }

        return new DTOFamiliaSinHabitat(
            evolutionChainId: $chainId,
            base: $base,
            evolutions: $evolutions,
        );
    }

    /**
     * @param  array<int, array{id: int, name: string, icon: string, stage: int}>  $members
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
}
