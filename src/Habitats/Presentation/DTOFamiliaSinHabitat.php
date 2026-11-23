<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

use Src\Habitats\Presentation\Concerns\FamiliaEvolutivaComun;

/**
 * DTO que representa una familia evolutiva sin hábitat asignado.
 */
class DTOFamiliaSinHabitat
{
    use FamiliaEvolutivaComun;

    public function __construct(
        public readonly int $evolutionChainId,
        public readonly DTOPokemonFamilia $base,
        public readonly ColeccionPokemonFamilia $evolutions,
        public readonly ColeccionTiposFamilia $types = new ColeccionTiposFamilia(),
    ) {
    }

    /**
     * Frontera: retorna el array esperado por la API / blades.
     *
     * @return array{evolution_chain_id: int, base: array{id: int, name: string, icon: string}, evolutions: array<int, array{id: int, name: string, icon: string}>, types: array<int, array{id: int, name: string}>}
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
