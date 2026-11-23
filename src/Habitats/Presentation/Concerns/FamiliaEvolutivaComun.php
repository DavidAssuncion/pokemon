<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation\Concerns;

use Src\Habitats\Presentation\DTOPokemonFamilia;

/**
 * Comportamiento común de las familias evolutivas de hábitats.
 *
 * Las clases que lo usan exponen `$base` (DTOPokemonFamilia) y `$evolutions`
 * (ColeccionPokemonFamilia); el `toArray()` se mantiene en cada DTO porque su
 * contrato de presentación difiere.
 */
trait FamiliaEvolutivaComun
{
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
}
