<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Collections;

use Src\Battle\Domain\Posicion;
use Src\Shared\Domain\Collection;

/**
 * Colección ordenada de posiciones de combate (vanguardia/retaguardia).
 * El índice de cada posición coincide con el slot del equipo.
 */
final class PosicionCollection extends Collection
{
    public string $type = Posicion::class;

    /**
     * @param  list<Posicion>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<Posicion> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?Posicion
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof Posicion ? $item : null;
    }

    public function cantidad(Posicion $posicion): int
    {
        return count(array_filter($this->items, static fn (Posicion $p): bool => $p === $posicion));
    }

    public function reemplazar(int $indice, Posicion $posicion): void
    {
        $this->items[$indice] = $posicion;
    }
}
