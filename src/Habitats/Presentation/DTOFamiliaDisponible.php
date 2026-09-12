<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO que representa una familia evolutiva disponible en un hábitat.
 */
class DTOFamiliaDisponible
{
    public function __construct(
        public readonly int $evolutionChainId,
        public readonly DTOPokemonFamilia $base,
        public readonly ColeccionPokemonFamilia $evolutions,
        public readonly ColeccionTiposFamilia $types = new ColeccionTiposFamilia(),
    ) {
    }

    public function contieneSpecies(int $speciesId): bool
    {
        if ($this->base->speciesId === $speciesId) {
            return true;
        }

        return $this->evolutions->contains(fn (DTOPokemonFamilia $miembro) => $miembro->speciesId === $speciesId);
    }

    /**
     * El species_id mínimo de la familia (el "primer integrante", criterio de negocio).
     */
    public function minSpeciesId(): int
    {
        return $this->evolutions->reduce(
            fn (int $min, DTOPokemonFamilia $miembro) => min($min, $miembro->speciesId),
            $this->base->speciesId,
        );
    }

    /**
     * Frontera: retorna el array esperado por la API / blades.
     *
     * @return array{evolution_chain_id: int, base: array{id: int, name: string, icon: string, level: int}, evolutions: array<int, array{id: int, name: string, icon: string, level: int}>, types: array<int, array{id: int, name: string}>}
     */
    public function toArray(): array
    {
        return [
            'evolution_chain_id' => $this->evolutionChainId,
            'base' => $this->base->toArray(),
            'evolutions' => $this->evolutions->toArray(),
            'types' => $this->types->toArray(),
        ];
    }
}
