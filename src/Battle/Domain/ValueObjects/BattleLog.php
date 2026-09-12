<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

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

    /**
     * Retorna las entradas como array indexado.
     *
     * Frontera — para serialización/compatibilidad con capas externas.
     * En F5b se migrará el consumo de los call-sites.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Retorna una nueva instancia sin entradas.
     */
    public function limpiar(): self
    {
        return new self();
    }

    /**
     * Retorna las últimas N entradas.
     *
     * @return list<string>
     */
    public function ultimas(int $n): array
    {
        return array_slice($this->entries, -$n);
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
