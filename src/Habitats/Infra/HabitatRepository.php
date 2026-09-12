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
use Src\Habitats\Domain\ResolvedorCadenasEvolutivas;
use Src\Habitats\Presentation\ColeccionTiposFamilia;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOFamiliaSinHabitat;
use Src\Habitats\Presentation\DTOFamiliasSinHabitat;
use Src\Habitats\Presentation\DTOHabitatDetalle;
use Src\Habitats\Presentation\DTOPokemonFamilia;
use Src\Habitats\Presentation\DTOPokemonNivelActualizado;
use Src\Habitats\Presentation\DTOPokemonTipo;

/**
 * Repositorio Eloquent de hábitats.
 *
 * El campo `icon` de los JSON servidos apunta a WebP optimizado:
 * `/images/iconos_webp/{id}.webp`. Los PNG originales quedan en
 * `/images/iconos/{id}.png` como fuente/fallback.
 */
class HabitatRepository implements HabitatRepositoryInterface
{
    private readonly ResolvedorCadenasEvolutivas $resolvedorCadenas;

    public function __construct()
    {
        $this->resolvedorCadenas = new ResolvedorCadenasEvolutivas(
            fn (int $id): string => $this->iconPath($id),
        );
    }

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

        $result = new DTOFamiliasDisponibles();

        foreach ($chainIds as $chainId) {
            $members = $this->cargarMiembrosFamilia($chainId);
            if ($members === []) {
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
            if ($members === []) {
                continue;
            }

            $family = $this->buildUnassignedFamilyFromChain($chainId, $members);
            if ($family !== null) {
                $result->add($family);
            }
        }

        return $result->ordenadasPorMinSpeciesId();
    }

    public function assignFamily(int $habitatId, int $evolutionChainId): DTOFamiliaDisponible
    {
        $this->assertHabitatExists($habitatId);

        $members = $this->cargarMiembrosFamilia($evolutionChainId);
        $this->assertFamilyMembersExist($evolutionChainId, $members);

        $totalStages = $this->resolvedorCadenas->totalStages($members);

        DB::transaction(function () use ($habitatId, $members, $totalStages) {
            $records = array_map(fn (array $member) => [
                'pokemon_id' => $member['id'],
                'habitat_id' => $habitatId,
                'level' => $this->resolvedorCadenas->levelForStage($member['stage'], $totalStages),
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

        $members = $this->cargarMiembrosFamilia($evolutionChainId);
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
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    private function buildAvailableFamilyFromChain(int $chainId, array $members): ?DTOFamiliaDisponible
    {
        $totalStages = $this->resolvedorCadenas->totalStages($members);

        [$base, $evolutions] = $this->resolvedorCadenas->splitFamilyMembers($members, fn (array $member): DTOPokemonFamilia => new DTOPokemonFamilia(
            id: $member['id'],
            name: $member['name'],
            icon: $this->iconPath($member['id']),
            level: $this->resolvedorCadenas->levelForStage($member['stage'], $totalStages),
            speciesId: $member['species_id'],
        ));

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
        [$base, $evolutions] = $this->resolvedorCadenas->splitFamilyMembers($members, fn (array $member): DTOPokemonFamilia => new DTOPokemonFamilia(
            id: $member['id'],
            name: $member['name'],
            icon: $this->iconPath($member['id']),
            level: null,
            speciesId: $member['species_id'],
        ));

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
     * @param  array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>  $members
     */
    private function getChainTypes(array $members): ColeccionTiposFamilia
    {
        $ids = array_map(fn (array $member) => $member['id'], $members);

        $tipos = PokemonType::whereIn('pokemon_id', $ids)
            ->get()
            ->pluck('type')
            ->map(fn (TipoEnum $type) => new DTOPokemonTipo(id: $type->value, name: $type->label()))
            ->unique(fn (DTOPokemonTipo $tipo) => $tipo->id)
            ->sortBy(fn (DTOPokemonTipo $tipo) => $tipo->id)
            ->values()
            ->all();

        return new ColeccionTiposFamilia($tipos);
    }

    /**
     * Carga los miembros de la cadena evolutiva (queries Eloquent) y delega el
     * cálculo de etapas por BFS en el resolvedor de dominio (puro).
     *
     * @return array<int, array{id: int, name: string, icon: string, stage: int, species_id: int}>
     */
    private function cargarMiembrosFamilia(int $chainId): array
    {
        $pokemon = Pokemon::where('evolution_chain_id', $chainId)
            ->get(['id', 'name', 'species_id'])
            ->sortBy([
                ['species_id', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (Pokemon $p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'species_id' => (int) $p->species_id,
            ])
            ->values()
            ->toArray();

        $ids = array_column($pokemon, 'id');

        $evolutions = PokemonEvolution::whereIn('evolved_species_id', $ids)
            ->get(['evolved_species_id', 'evolves_from_species_id'])
            ->map(fn ($row) => [
                'evolved_species_id' => (int) $row['evolved_species_id'],
                'evolves_from_species_id' => $row['evolves_from_species_id'] !== null ? (int) $row['evolves_from_species_id'] : null,
            ])
            ->values()
            ->toArray();

        return $this->resolvedorCadenas->getFamilyMembersByChain($pokemon, $evolutions);
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
