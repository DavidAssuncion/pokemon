<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\DataTransferObjects;

/**
 * Un caramelo del modal de victoria (familia/EV/tipo resuelto por ItemCatalogo).
 *
 * Sustituye el array {nombre, imagen, cantidad} (deuda F6).
 */
final readonly class ItemCaramelo
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $imagen,
        public readonly int $cantidad,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{nombre: string, imagen: string, cantidad: int}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'imagen' => $this->imagen,
            'cantidad' => $this->cantidad,
        ];
    }
}
