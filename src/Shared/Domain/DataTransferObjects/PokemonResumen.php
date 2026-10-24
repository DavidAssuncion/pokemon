<?php

declare(strict_types=1);

namespace Src\Shared\Domain\DataTransferObjects;

/**
 * Representación mínima de un pokémon para listados y modales:
 * id, nombre e icono WebP.
 */
final readonly class PokemonResumen
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $icono,
    ) {
    }

    public static function desdeIdNombre(int $id, string $nombre): self
    {
        return new self($id, $nombre, "/images/iconos_webp/{$id}.webp");
    }

    /**
     * Frontera — shape consumido por la API/UI.
     *
     * @return array{id: int, name: string, icon: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->nombre,
            'icon' => $this->icono,
        ];
    }
}
