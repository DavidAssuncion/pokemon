<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO de respuesta tras mover un pokémon a un nuevo nivel dentro de un hábitat.
 */
class DTOPokemonNivelActualizado
{
    public function __construct(
        public readonly int $habitatId,
        public readonly int $pokemonId,
        public readonly int $previousLevel,
        public readonly int $newLevel,
    ) {
    }

    /**
     * @return array{habitat_id: int, pokemon_id: int, previous_level: int, new_level: int}
     */
    public function toArray(): array
    {
        return [
            'habitat_id' => $this->habitatId,
            'pokemon_id' => $this->pokemonId,
            'previous_level' => $this->previousLevel,
            'new_level' => $this->newLevel,
        ];
    }
}
