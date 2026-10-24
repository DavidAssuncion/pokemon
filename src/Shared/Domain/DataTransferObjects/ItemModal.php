<?php

declare(strict_types=1);

namespace Src\Shared\Domain\DataTransferObjects;

/**
 * Item mostrado en un modal de recompensas.
 */
final readonly class ItemModal
{
    public function __construct(
        public readonly string $src,
        public readonly string $alt,
        public readonly int $cantidad,
        public readonly ?string $nombre,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato de la vista.
     *
     * @return array{src: string, alt: string, cantidad: int, nombre: string|null}
     */
    public function toArray(): array
    {
        return [
            'src' => $this->src,
            'alt' => $this->alt,
            'cantidad' => $this->cantidad,
            'nombre' => $this->nombre,
        ];
    }
}
