<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\ValueObjects;

use Src\Shared\Collections\StringCollection;

/**
 * Caramelos de tipo disponibles en el inventario del jugador.
 *
 * Sustituye el mapa `item_key => cantidad` que circulaba entre los métodos
 * privados de ServicioEvolucion.
 */
final readonly class InventarioCaramelos
{
    /**
     * @param  array<string, int>  $cantidades  item_key => cantidad
     */
    public function __construct(
        private array $cantidades = [],
    ) {
    }

    public static function vacio(): self
    {
        return new self();
    }

    public function cantidad(string $itemKey): int
    {
        return $this->cantidades[$itemKey] ?? 0;
    }

    public function tiene(string $itemKey): bool
    {
        return $this->cantidad($itemKey) > 0;
    }

    public function claves(): StringCollection
    {
        return new StringCollection(array_keys($this->cantidades));
    }
}
