<?php

declare(strict_types=1);

namespace Src\Habitats\Infra\Concerns;

use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Domain\Collections\EvolucionCadenaCollection;
use Src\Habitats\Domain\Collections\MiembrosCadenaCollection;
use Src\Habitats\Domain\Collections\PokemonCadenaCollection;
use Src\Habitats\Domain\DataTransferObjects\EvolucionCadena;
use Src\Habitats\Domain\DataTransferObjects\MiembroCadena;
use Src\Habitats\Domain\DataTransferObjects\PokemonCadenaBase;
use Src\Habitats\Domain\ResolvedorCadenasEvolutivas;
use Src\Habitats\Presentation\ColeccionTiposFamilia;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaSinHabitat;
use Src\Habitats\Presentation\DTOPokemonFamilia;
use Src\Habitats\Presentation\DTOPokemonTipo;

/**
 * Consultas compartidas de familias evolutivas para los repositorios de
 * lectura y gestión de hábitats (evita duplicar el BFS y el mapeo).
 */
trait CadenasFamiliaHabitatsTrait
{
    private ?ResolvedorCadenasEvolutivas $resolvedorCadenas = null;

    private function resolvedor(): ResolvedorCadenasEvolutivas
    {
        return $this->resolvedorCadenas ??= new ResolvedorCadenasEvolutivas(
            fn (int $id): string => $this->iconPath($id),
        );
    }

    /**
     * @param  array<int, array{level: int, min_level: int|null, manual: bool}>|null  $pivotes
     */
    protected function buildAvailableFamilyFromChain(int $chainId, MiembrosCadenaCollection $members, ?array $pivotes = null): ?DTOFamiliaDisponible
    {
        $totalStages = $this->resolvedor()->totalStages($members);

        $reparto = $this->resolvedor()->splitFamilyMembers($members, function (MiembroCadena $member) use ($totalStages, $pivotes): DTOPokemonFamilia {
            $pivote = $pivotes[$member->id] ?? null;

            return new DTOPokemonFamilia(
                id: $member->id,
                name: $member->nombre,
                icon: $this->iconPath($member->id),
                level: $pivote !== null
                    ? $pivote['level']
                    : $this->resolvedor()->levelForStage($member->stage, $totalStages),
                speciesId: $member->speciesId,
                stage: $member->stage,
                parentId: $member->parentId,
                minLevel: $pivote['min_level'] ?? null,
                manual: $pivote['manual'] ?? false,
            );
        });

        $base = $reparto->base;
        $evolutions = $reparto->evoluciones;

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

    protected function buildUnassignedFamilyFromChain(int $chainId, MiembrosCadenaCollection $members): ?DTOFamiliaSinHabitat
    {
        $reparto = $this->resolvedor()->splitFamilyMembers($members, fn (MiembroCadena $member): DTOPokemonFamilia => new DTOPokemonFamilia(
            id: $member->id,
            name: $member->nombre,
            icon: $this->iconPath($member->id),
            level: null,
            speciesId: $member->speciesId,
            stage: $member->stage,
            parentId: $member->parentId,
        ));

        $base = $reparto->base;
        $evolutions = $reparto->evoluciones;

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

    protected function getChainTypes(MiembrosCadenaCollection $members): ColeccionTiposFamilia
    {
        $ids = $members->ids();

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
     */
    protected function cargarMiembrosFamilia(int $chainId): MiembrosCadenaCollection
    {
        $pokemon = new PokemonCadenaCollection(
            Pokemon::where('evolution_chain_id', $chainId)
                ->get(['id', 'name', 'species_id'])
                ->sortBy([
                    ['species_id', 'asc'],
                    ['id', 'asc'],
                ])
                ->map(fn (Pokemon $p): PokemonCadenaBase => new PokemonCadenaBase(
                    id: $p->id,
                    nombre: $p->name,
                    speciesId: $p->species_id,
                ))
                ->values()
                ->all()
        );

        $ids = $pokemon->map(fn (PokemonCadenaBase $p): int => $p->id);

        $evolutions = new EvolucionCadenaCollection(
            PokemonEvolution::whereIn('evolved_species_id', $ids)
                ->get(['evolved_species_id', 'evolves_from_species_id'])
                ->map(fn ($row): EvolucionCadena => new EvolucionCadena(
                    evolvedSpeciesId: (int) $row['evolved_species_id'],
                    evolvesFromSpeciesId: $row['evolves_from_species_id'] !== null ? (int) $row['evolves_from_species_id'] : null,
                ))
                ->values()
                ->all()
        );

        return $this->resolvedor()->getFamilyMembersByChain($pokemon, $evolutions);
    }

    protected function assertHabitatExists(int $habitatId): void
    {
        if (Habitat::find($habitatId) === null) {
            throw new \InvalidArgumentException("El hábitat {$habitatId} no existe");
        }
    }

    protected function assertFamilyMembersExist(int $evolutionChainId, MiembrosCadenaCollection $members): void
    {
        if ($members->isEmpty()) {
            throw new \InvalidArgumentException("La cadena evolutiva {$evolutionChainId} no existe o no tiene pokémon");
        }
    }

    protected function iconPath(int $pokemonId): string
    {
        return "/images/iconos_webp/{$pokemonId}.webp";
    }

    /**
     * Pivotes reales (tier, min_level, manual) de un hábitat para los Pokémon
     * indicados, indexados por pokemon_id. Sin `$pokemonIds` carga todos los
     * del hábitat.
     *
     * @param  list<int>|null  $pokemonIds
     * @return array<int, array{level: int, min_level: int|null, manual: bool}>
     */
    protected function pivotesDeHabitat(int $habitatId, ?array $pokemonIds = null): array
    {
        if ($pokemonIds === []) {
            return [];
        }

        $pivotes = [];

        $query = DB::table('pokemon_habitat')
            ->where('habitat_id', $habitatId);

        if ($pokemonIds !== null) {
            $query->whereIn('pokemon_id', $pokemonIds);
        }

        $rows = $query->get(['pokemon_id', 'level', 'min_level', 'manual']);

        foreach ($rows as $row) {
            $pivotes[(int) $row->pokemon_id] = [
                'level' => (int) $row->level,
                'min_level' => $row->min_level !== null ? (int) $row->min_level : null,
                'manual' => (bool) $row->manual,
            ];
        }

        return $pivotes;
    }
}
