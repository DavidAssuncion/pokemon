<?php

declare(strict_types=1);

namespace App\Support\Pokedex;

use JsonSerializable;

/**
 * Opción de un filtro de la Pokédex, serializable en la frontera de vista.
 */
final readonly class OpcionFiltro implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    /**
     * @return array{id: int, name: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
