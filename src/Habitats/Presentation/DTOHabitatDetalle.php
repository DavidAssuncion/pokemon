<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO para el detalle de un hábitat.
 * Reemplaza el array asociativo retornado por HabitatRepository::getHabitatDetail().
 */
class DTOHabitatDetalle
{
    /**
     * @param  array<int, array<int, array{id: int, name: string, icon: string, min_level: int|null}>>  $levels
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $image,
        public readonly array $levels,
        public readonly ?int $min_lvl_1 = null,
        public readonly ?int $min_lvl_2 = null,
        public readonly ?int $min_lvl_3 = null,
        public readonly bool $sinSpawns = false,
    ) {
    }

    /**
     * @return array{id: int, name: string, image: string, levels: array<int, array<int, array{id: int, name: string, icon: string, min_level: int|null}>>, min_lvl_1: ?int, min_lvl_2: ?int, min_lvl_3: ?int, sin_spawns: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'levels' => $this->levels,
            'min_lvl_1' => $this->min_lvl_1,
            'min_lvl_2' => $this->min_lvl_2,
            'min_lvl_3' => $this->min_lvl_3,
            'sin_spawns' => $this->sinSpawns,
        ];
    }
}
