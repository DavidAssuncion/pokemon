<?php

declare(strict_types=1);

namespace Src\Shared\Collections;

use Src\Shared\Domain\Collection;

/**
 * Colección de cadenas. Src\Shared\Domain\Collection valida con
 * `instanceof`, que no aplica a escalares; se valida con is_string().
 *
 * La entrada `string[]` es aceptable SOLO en frontera; el dominio
 * no expone string[].
 */
final class StringCollection extends Collection
{
    public string $type = 'string';

    /**
     * @param  mixed  $item
     */
    protected function validateType($item): void
    {
        if (! is_string($item)) {
            throw new \InvalidArgumentException('Invalid type');
        }
    }

    /**
     * Une todas las cadenas con el separador.
     */
    public function concatenar(string $separador): string
    {
        return implode($separador, $this->items);
    }

    /**
     * Primera cadena de la colección o null si está vacía.
     */
    public function primera(): ?string
    {
        return $this->items[0] ?? null;
    }

    /**
     * Verifica si la colección contiene la cadena dada.
     */
    public function contiene(string $valor): bool
    {
        return in_array($valor, $this->items, true);
    }

    /**
     * Nueva colección con las cadenas únicas (primera aparición).
     */
    public function unique(): static
    {
        return new static(array_values(array_unique($this->items)));
    }

    /** @return list<string> */
    public function toList(): array
    {
        return $this->items;
    }
}
