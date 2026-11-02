<?php

declare(strict_types=1);

namespace Src\Habitats\Infra;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Domain\Collections\PokemonBasicoCollection;
use Src\Habitats\Domain\DataTransferObjects\PokemonBasico;
use Src\Habitats\Domain\HabitatEntity;
use Src\Habitats\Domain\HabitatsCollection;
use Src\Habitats\Domain\ProvinceEntity;
use Src\Habitats\Domain\ProvinciasCollection;
use Src\Habitats\Domain\Repositories\HabitatLecturaRepositoryInterface;
use Src\Habitats\Infra\Concerns\CadenasFamiliaHabitatsTrait;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;
use Src\Habitats\Presentation\DTOHabitatDetalle;

/**
 * Lectura Eloquent de provincias, hábitats y familias. Sin mutaciones.
 *
 * El campo `icon` de los JSON servidos apunta a WebP optimizado:
 * `/images/iconos_webp/{id}.webp`. Los PNG originales quedan en
 * `/images/iconos/{id}.png` como fuente/fallback.
 */
class HabitatLecturaRepository implements HabitatLecturaRepositoryInterface
{
    use CadenasFamiliaHabitatsTrait;

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

    public function getPokemonsByHabitat(int $habitatId): PokemonBasicoCollection
    {
        $habitat = Habitat::find($habitatId);
        if ($habitat === null) {
            return new PokemonBasicoCollection();
        }

        $pokemons = $habitat->pokemon()
            ->select(['pokemon.id', 'pokemon.name'])
            ->get()
            ->map(fn ($pokemon): PokemonBasico => new PokemonBasico(
                id: (int) $pokemon->id,
                name: (string) $pokemon->name,
            ))
            ->all();

        return new PokemonBasicoCollection($pokemons);
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

        $imagePath = resource_path("habitats_img/{$habitat->id}.webp");
        $imageVersion = is_file($imagePath) ? filemtime($imagePath) : 0;

        return new DTOHabitatDetalle(
            id: $habitat->id,
            name: $habitat->name,
            image: "/habitats-img/{$habitat->id}.webp?v={$imageVersion}",
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

        $result = new DTOFamiliasDisponibles();

        foreach ($chainIds as $chainId) {
            $members = $this->cargarMiembrosFamilia($chainId);
            if ($members->isEmpty()) {
                continue;
            }

            $family = $this->buildAvailableFamilyFromChain($chainId, $members);
            if ($family !== null) {
                $result->add($family);
            }
        }

        return $result->ordenadasPorMinSpeciesId();
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
            $members = $this->cargarMiembrosFamilia($chainId);
            if ($members->isEmpty()) {
                continue;
            }

            $family = $this->buildUnassignedFamilyFromChain($chainId, $members);
            if ($family !== null) {
                $result->add($family);
            }
        }

        return $result->ordenadasPorMinSpeciesId();
    }
}
