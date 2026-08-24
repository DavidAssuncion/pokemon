<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\MovimientoBatalla;

/**
 * Colección tipada de MovimientoBatalla.
 *
 * No extiende Illuminate\Support\Collection porque está en src/
 * y no puede depender de Illuminate. Usa ArrayIterator directamente.
 *
 * @implements \IteratorAggregate<int, MovimientoBatalla>
 */
class ColeccionMovimientos implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @var MovimientoBatalla[] */
    private array $items = [];

    /**
     * @param  MovimientoBatalla[]  $items
     */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->validar($item);
            $this->items[] = $item;
        }
    }

    private function validar(mixed $item): void
    {
        if (! $item instanceof MovimientoBatalla) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La colección solo acepta instancias de MovimientoBatalla, se recibió %s',
                    get_debug_type($item)
                )
            );
        }
    }

    public function add(MovimientoBatalla $item): void
    {
        $this->items[] = $item;
    }

    public function get(int $index): ?MovimientoBatalla
    {
        return $this->items[$index] ?? null;
    }

    /**
     * @return MovimientoBatalla[]
     */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->validar($value);
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
        $this->items = array_values($this->items);
    }
}
