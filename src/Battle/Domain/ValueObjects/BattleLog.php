<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Shared\Collections\StringCollection;

/**
 * Value Object inmutable que encapsula el registro de eventos de una batalla.
 *
 * Cada operación de mutación retorna una nueva instancia,
 * preservando la semántica inmutable del VO.
 */
final class BattleLog
{
    /** @param list<string> $entries */
    private function __construct(
        private readonly array $entries = [],
    ) {
    }

    public static function vacia(): self
    {
        return new self();
    }

    /**
     * Retorna una nueva instancia con la entrada agregada al final.
     */
    public function agregar(string $entrada): self
    {
        return new self([...$this->entries, $entrada]);
    }

    public function entries(): StringCollection
    {
        return new StringCollection($this->entries);
    }

    /**
     * Retorna una nueva instancia sin entradas.
     */
    public function limpiar(): self
    {
        return new self();
    }

    public function ultimas(int $n): StringCollection
    {
        return new StringCollection(array_slice($this->entries, -$n));
    }

    /**
     * Indica si el registro tiene alguna entrada.
     */
    public function tieneContenido(): bool
    {
        return $this->entries !== [];
    }

    /**
     * Serializa a array indexado.
     *
     * Frontera — para compatibilidad con capas que aún esperan array.
     *
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
