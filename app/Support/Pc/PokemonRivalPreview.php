<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * Pokémon rival anónimo ("Rival 1", "Rival 2", ...) con sus stats.
 */
final readonly class PokemonRivalPreview
{
    public function __construct(
        public string $etiqueta,
        public StatsPreview $stats,
    ) {
    }

    /**
     * @return array{
     *     etiqueta: string,
     *     stats: list<array{
     *         clave: string,
     *         etiqueta: string,
     *         base: int,
     *         iv: int,
     *         ev: int,
     *         calculado: int
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'etiqueta' => $this->etiqueta,
            'stats' => $this->stats->toArray(),
        ];
    }
}
