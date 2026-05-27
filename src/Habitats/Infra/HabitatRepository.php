<?php

namespace Src\Habitats\Infra;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\Province;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Habitats\Domain\HabitatEntity;
use Src\Habitats\Domain\HabitatsCollection;
use Src\Habitats\Domain\ProvinceEntity;
use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Domain\Repositories\HabitatRepositoryInterface;

class HabitatRepository implements HabitatRepositoryInterface
{
    public function allProvinciasWithHabitats(): ProvinciasCollection
    {
        $provinces = Province::with('habitats')->get();

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

    public function getPokemonsByHabitat(int $habitatId): array
    {
        $habitat = Habitat::find($habitatId);
        if (!$habitat) {
            return [];
        }

        return $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name'])
            ->get()
            ->map(fn($pokemon) => [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
            ])
            ->toArray();
    }

    public function getHabitatDetail(int $habitatId): array
    {
        $habitat = Habitat::find($habitatId);
        if (!$habitat) {
            return [];
        }

        $speciesIds = $habitat->pokemon()
            ->pluck('species_id')
            ->unique()
            ->toArray();

        $chainMapping = [];
        if (Schema::hasTable('pokemon_species')) {
            $chainMapping = DB::table('pokemon_species')
                ->whereIn('id', $speciesIds)
                ->pluck('evolution_chain_id', 'id')
                ->toArray();
        }

        $habitatPokemon = $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name', 'pokemon.species_id'])
            ->get();

        $familyChains = [];
        $unmapped = [];
        foreach ($habitatPokemon as $pokemon) {
            $chainId = $chainMapping[$pokemon->species_id] ?? null;
            if ($chainId) {
                $familyChains[$chainId][] = $pokemon->species_id;
            } else {
                $unmapped[$pokemon->species_id] = $pokemon;
            }
        }

        $levels = [1 => [], 2 => [], 3 => []];

        foreach ($unmapped as $pokemon) {
            $levels[2][] = [
                'id' => $pokemon->id,
                'name' => $pokemon->name,
            ];
        }

        foreach (array_keys($familyChains) as $chainId) {
            if (Schema::hasTable('pokemon_species')) {
                $chainSpeciesIds = DB::table('pokemon_species')
                    ->where('evolution_chain_id', $chainId)
                    ->pluck('id')
                    ->toArray();
            } else {
                $chainEvos = PokemonEvolution::where('evolution_chain_id', $chainId)->get();
                $chainSpeciesIds = [];
                foreach ($chainEvos as $e) {
                    $chainSpeciesIds[] = $e->evolved_species_id;
                    if (!empty($e->evolves_from_species_id)) {
                        $chainSpeciesIds[] = $e->evolves_from_species_id;
                    }
                }
                $chainSpeciesIds = array_values(array_unique(array_filter($chainSpeciesIds)));
                if (empty($chainSpeciesIds)) {
                    $chainSpeciesIds = $familyChains[$chainId] ?? [];
                }
            }

            $evolutions = PokemonEvolution::where('evolution_chain_id', $chainId)->get();
            $orderedSpecies = $this->orderSpeciesByEvolution($chainSpeciesIds, $evolutions);
            $familyPokemons = Pokemon::whereIn('species_id', $chainSpeciesIds)
                ->select(['id', 'name', 'species_id'])
                ->get()
                ->groupBy('species_id');

            $count = count($orderedSpecies);
            foreach ($orderedSpecies as $index => $species) {
                $level = $this->getEvolutionLevel($index, $count);
                foreach ($familyPokemons[$species] ?? [] as $pokemon) {
                    $levels[$level][] = [
                        'id' => $pokemon->id,
                        'name' => $pokemon->name,
                    ];
                }
            }
        }

        return [
            'id' => $habitat->id,
            'name' => $habitat->name,
            'image' => "/habitats-img/{$habitat->id}.webp",
            'levels' => $levels,
        ];
    }

    private function orderSpeciesByEvolution(array $speciesIds, $evolutions): array
    {
        $children = [];
        $parents = [];

        foreach ($evolutions as $evolution) {
            $children[$evolution->evolves_from_species_id][] = $evolution->evolved_species_id;
            $parents[$evolution->evolved_species_id] = $evolution->evolves_from_species_id;
        }

        $roots = array_values(array_filter($speciesIds, fn ($id) => !isset($parents[$id])));
        if (empty($roots)) {
            $roots = [$speciesIds[0]];
        }

        $order = [];
        foreach ($roots as $root) {
            $this->traverseSpecies($root, $children, $order);
        }

        $ordered = array_values(array_unique(array_filter($order, fn ($id) => in_array($id, $speciesIds, true))));
        if (empty($ordered)) {
            return $speciesIds;
        }

        return $ordered;
    }

    private function traverseSpecies(int $speciesId, array $children, array &$order): void
    {
        if (in_array($speciesId, $order, true)) {
            return;
        }

        $order[] = $speciesId;
        foreach ($children[$speciesId] ?? [] as $child) {
            $this->traverseSpecies($child, $children, $order);
        }
    }

    private function getEvolutionLevel(int $index, int $count): int
    {
        if ($count === 1) {
            return 2;
        }

        if ($count === 2) {
            return 2 + $index;
        }

        if ($count === 3) {
            return 1 + $index;
        }

        if ($index <= 1) {
            return 1;
        }

        if ($index === $count - 1) {
            return 3;
        }

        return 2;
    }
}
