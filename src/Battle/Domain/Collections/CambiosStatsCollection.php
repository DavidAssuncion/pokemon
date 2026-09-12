<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de cambios de estadísticas pendientes declarados por
 * un movimiento (selfStatChanges / targetStatChanges).
 *
 * No confundir con ModificadoresStatsCollection: esta colección representa
 * la INTENCIÓN (cambios sin aplicar), no el estado de factores de un Combatiente.
 */
final class CambiosStatsCollection extends Collection
{
    public string $type = CambioStat::class;

    /**
     * Retorna todos los elementos como array indexado.
     *
     * @return list<CambioStat>
     */
    public function all(): array
    {
        return $this->items;
    }
}
