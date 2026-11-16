<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\DataTransferObjects;

/**
 * Representación compacta de un reclutable para el listado y el modal de
 * reclutamiento.
 */
final readonly class ReclutableResumen
{
    public function __construct(
        public int $id,
        public int $pokemonId,
        public string $nombre,
        public string $icono,
        public int $cantidad,
    ) {
    }

    /**
     * Frontera — shape exacto consumido por la API/UI.
     *
     * @return array{
     *     id: int,
     *     pokemon_id: int,
     *     nombre: string,
     *     icono: string,
     *     cantidad: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pokemon_id' => $this->pokemonId,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            'cantidad' => $this->cantidad,
        ];
    }
}
