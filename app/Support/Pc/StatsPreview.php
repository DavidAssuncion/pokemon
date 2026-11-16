<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * Conjunto ordenado de stats (PS → Velocidad) de un Pokémon rival.
 */
final readonly class StatsPreview
{
    /**
     * @param  list<StatPreview>  $stats
     */
    public function __construct(
        public array $stats,
    ) {
    }

    /**
     * @return list<array{
     *     clave: string,
     *     etiqueta: string,
     *     base: int,
     *     iv: int,
     *     ev: int,
     *     calculado: int
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (StatPreview $stat): array => $stat->toArray(),
            $this->stats,
        );
    }
}
