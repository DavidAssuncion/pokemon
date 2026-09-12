<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

class Collection implements \IteratorAggregate
{
    public string $type;

    protected array $items = [];

    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function add($item): void
    {
        $this->validateType($item);
        $this->items[] = $item;
    }

    protected function validateType($item): void
    {
        if (! ($item instanceof $this->type)) {
            throw new \InvalidArgumentException('Invalid type');
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function filter(callable $callback): static
    {
        return new static(array_filter($this->items, $callback));
    }

    /**
     * Retorna el primer elemento que cumple el predicado o, sin predicado,
     * el primer elemento de la lista. null si no hay coincidencia o está vacía.
     *
     * @param  callable(object): bool|null  $predicate
     * @return object|null Primer elemento (tipado según $type de la subclase)
     */
    public function first(?callable $predicate = null): ?object
    {
        if ($predicate === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Retorna una NUEVA colección ordenada con el comparador (inmutable:
     * la original no se modifica). El orden es ESTABLE (usort de PHP 8).
     *
     * @param  callable(object, object): int  $cmp
     * @return static Nueva instancia ordenada de la misma subclase
     */
    public function sort(callable $cmp): static
    {
        $items = $this->items;
        usort($items, $cmp);

        return new static($items);
    }

    /**
     * Transforma cada elemento aplicando el callback.
     *
     * Retorna un array plano: usar SOLO para transformación saliente (toArray, etc.).
     * NO usar para lógica de dominio.
     *
     * @template TMap
     *
     * @param  callable(object): TMap  $callback
     * @return list<TMap>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * Extrae un valor de cada elemento mediante el callback.
     *
     * @template TPluck of scalar
     *
     * @param  callable(object): TPluck  $extractor
     * @return list<TPluck>
     */
    public function pluck(callable $extractor): array
    {
        return array_map($extractor, $this->items);
    }

    /**
     * Reduce la colección a un solo valor.
     *
     * @template TReduce
     *
     * @param  callable(TReduce, object): TReduce  $carry
     * @param  TReduce  $initial
     * @return TReduce
     */
    public function reduce(callable $carry, mixed $initial): mixed
    {
        return array_reduce($this->items, $carry, $initial);
    }

    /**
     * Retorna los elementos como array indexado.
     *
     * Usar SOLO en frontera (toArray de DTOs, serialización). NO fomentar en dominio.
     *
     * @return list<mixed>
     */
    public function toList(): array
    {
        return $this->items;
    }

    /**
     * Verifica si algún elemento cumple el callback.
     */
    public function contains(callable $callback): bool
    {
        return $this->reduce(
            fn (bool $carry, object $item): bool => $carry || $callback($item),
            false,
        );
    }

    /**
     * Verifica que TODOS los elementos cumplan el callback.
     * Una colección vacía devuelve true (verdad vacua).
     */
    public function every(callable $callback): bool
    {
        return $this->reduce(
            fn (bool $carry, object $item): bool => $carry && $callback($item),
            true,
        );
    }

    /**
     * Suma un valor numérico extraído de cada elemento.
     *
     * @param  callable(object): float|int  $extractor
     */
    public function sum(callable $extractor): float|int
    {
        return array_sum($this->pluck($extractor));
    }

    /**
     * Mínimo valor de extractor(item). null si la colección está vacía
     * (valor por defecto documentado).
     *
     * @param  callable(object): scalar  $extractor
     */
    public function min(callable $extractor): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return min($this->pluck($extractor));
    }

    /**
     * Máximo valor de extractor(item). null si la colección está vacía
     * (valor por defecto documentado).
     *
     * @param  callable(object): scalar  $extractor
     */
    public function max(callable $extractor): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return max($this->pluck($extractor));
    }
}
