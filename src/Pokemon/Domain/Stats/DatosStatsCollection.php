<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de stats base de pokémon.
 *
 * Sustituye los `list<DatosStats>` que circulaban entre capas
 * (clasificación de formación, generadores de equipos).
 */
final class DatosStatsCollection extends Collection
{
    public string $type = DatosStats::class;

    /**
     * @param  list<DatosStats>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<DatosStats> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?DatosStats
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof DatosStats ? $item : null;
    }
}
