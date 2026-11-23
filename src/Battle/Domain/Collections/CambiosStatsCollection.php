<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\Enums\StatClave;
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
     * Reconstruye la colección desde el payload serializado.
     *
     * Acepta el formato actual ({stat, factor, etapas}) y el legacy v9
     * ({stat, stages}). Fuente única para `MovimientoBatalla` y el DTO de
     * presentación, que antes duplicaban esta lógica.
     *
     * @param  array<int, array<string, mixed>>  $cambios
     */
    public static function desdeSerializado(array $cambios): self
    {
        $items = [];

        foreach ($cambios as $cambio) {
            $stat = StatClave::tryFrom((string) ($cambio['stat'] ?? ''));
            if ($stat === null) {
                continue;
            }

            if (array_key_exists('factor', $cambio)) {
                $items[] = new CambioStat($stat, (float) $cambio['factor'], (int) ($cambio['etapas'] ?? 0));
            } elseif (array_key_exists('stages', $cambio)) {
                $items[] = CambioStat::desdeEtapas($stat, (int) $cambio['stages']);
            }
        }

        return new self($items);
    }

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
