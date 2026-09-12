<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de StatsDelPool (stats con effort > 0 del pool de hábitat).
 */
final class ColeccionStatsDelPool extends Collection
{
    public string $type = StatDelPool::class;

    /**
     * Frontera — lee una lista plana del contrato del pool.
     *
     * @param  list<array{stat?: int, effort?: int}>  $stats
     */
    public static function desdeLista(array $stats): self
    {
        return new self(array_map(
            static fn (array $stat): StatDelPool => StatDelPool::desdeArray($stat),
            $stats,
        ));
    }

    /**
     * Frontera — shape exacto del contrato previo del pool.
     *
     * @return list<array{stat: int, effort: int}>
     */
    public function aLista(): array
    {
        return array_map(
            static fn (StatDelPool $stat): array => $stat->aArray(),
            $this->items,
        );
    }
}
