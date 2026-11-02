<?php

declare(strict_types=1);

namespace Src\Habitats\Infra\Concerns;

use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Models\PokemonType;
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

    protected function buildAvailableFamilyFromChain(int $chainId, MiembrosCadenaCollection $members): ?DTOFamiliaDisponible
    {
        $totalStages = $this->resolvedor()->totalStages($members);

        $reparto = $this->resolvedor()->splitFamilyMembers($members, fn (MiembroCadena $member): DTOPokemonFamilia => new DTOPokemonFamilia(
            id: $member->id,
            name: $member->nombre,
            icon: $this->iconPath($member->id),
            level: $this->resolvedor()->levelForStage($member->stage, $totalStages),
            speciesId: $member->speciesId,
        ));

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

    protected function minLvlNullable(mixed $valor): ?int
    {
        return $valor !== null ? (int) $valor : null;
    }
}
