<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Evolution;

/**
 * Nodo de una familia evolutiva: datos mínimos de un Pokémon ya cargados por
 * la infraestructura de lectura.
 *
 * El resolvedor usa `speciesId` para interpretar las aristas de
 * `pokemon_evolution` y `id` para identificar el Pokémon en la salida.
 */
final readonly class PokemonFamilia
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly int $speciesId,
    ) {
    }
}
