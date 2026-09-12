<?php

declare(strict_types=1);

namespace Src\Habitats\Presentation;

/**
 * DTO tipado para un miembro de familia evolutiva (base o evolución).
 *
 * `id` es el id de pokémon (frontera: campo `id` de la API e icono WebP);
 * `speciesId` es el id de especie usado por la regla de negocio "primer
 * integrante = menor species_id" (el seeder los mantiene iguales; el DTO los
 * separa para que el orden por species_id no dependa del id de pokémon).
 */
final class DTOPokemonFamilia
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $icon,
        public readonly ?int $level,
        public readonly int $speciesId,
    ) {
    }

    /**
     * Frontera: retorna el array esperado por la API / blades.
     * El campo `level` se incluye solo cuando no es null (familias asignadas).
     *
     * @return array{id: int, name: string, icon: string, level?: int}
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
        ];

        if ($this->level !== null) {
            $data['level'] = $this->level;
        }

        return $data;
    }
}
