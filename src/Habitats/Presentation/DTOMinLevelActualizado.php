<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * Resultado de actualizar el nivel mínimo de un spawn concreto.
 *
 * `manual` indica si el valor queda fijado por el administrador y, por tanto,
 * el recálculo automático no lo pisará.
 */
final readonly class DTOMinLevelActualizado
{
    public function __construct(
        public int $habitatId,
        public int $pokemonId,
        public ?int $previousMinLevel,
        public int $newMinLevel,
        public bool $manual,
    ) {
    }

    /**
     * @return array{habitat_id: int, pokemon_id: int, previous_min_level: int|null, new_min_level: int, manual: bool}
     */
    public function toArray(): array
    {
        return [
            'habitat_id' => $this->habitatId,
            'pokemon_id' => $this->pokemonId,
            'previous_min_level' => $this->previousMinLevel,
            'new_min_level' => $this->newMinLevel,
            'manual' => $this->manual,
        ];
    }
}
