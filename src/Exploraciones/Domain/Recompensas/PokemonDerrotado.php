<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Illuminate\Support\Collection;

/**
 * Pokémon derrotado en una exploración, normalizado para el calculador de
 * recompensas (dominio puro, sin Eloquent).
 */
final class PokemonDerrotado
{
    /**
     * @param  list<string>  $tipos  Labels en español de los tipos (p. ej. 'Eléctrico').
     * @param  Collection<int, array{stat: int, effort: int}>  $stats
     */
    public function __construct(
        public readonly int $id,
        public readonly int $baseExperience,
        public readonly ?int $evolutionChainId,
        public readonly int $speciesId,
        public readonly int $captureRate,
        public readonly array $tipos,
        public readonly Collection $stats,
        public readonly int $fase,
    ) {
    }
}
