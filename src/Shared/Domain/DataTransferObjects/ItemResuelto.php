<?php

declare(strict_types=1);

namespace Src\Shared\Domain\DataTransferObjects;

/**
 * Presentación resuelta de un item de caramelo (familia/EV/tipo).
 */
final readonly class ItemResuelto
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $imagen,
        public readonly string $categoria,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato JSON previo.
     *
     * @return array{nombre: string, imagen: string, categoria: string}
     */
    public function toArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'imagen' => $this->imagen,
            'categoria' => $this->categoria,
        ];
    }
}
