<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de pares (StatClave, factor) que representa
 * todos los modificadores multiplicativos de un combatiente.
 */
final class ModificadoresStatsCollection extends Collection
{
    public string $type = ModificadorStat::class;

    /**
     * Retorna los factores indexados por clave de estadística.
     *
     * @return array<string, float>
     */
    public function factores(): array
    {
        $resultado = [];
        foreach ($this->items as $item) {
            /** @var ModificadorStat $item */
            $resultado[$item->statValue()] = $item->factor;
        }

        return $resultado;
    }

    /**
     * Retorna una nueva colección ordenada por el valor de la estadística.
     */
    public function ordenarPorStat(): static
    {
        $items = $this->items;
        usort($items, fn (ModificadorStat $a, ModificadorStat $b) => $a->statValue() <=> $b->statValue());

        return new static($items);
    }

    /**
     * Retorna todos los elementos como array indexado.
     *
     * @return list<ModificadorStat>
     */
    public function all(): array
    {
        return $this->items;
    }
}
