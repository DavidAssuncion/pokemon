<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\DataTransferObjects;

/**
 * Datos mínimos de un pokémon que forma parte de una cadena evolutiva,
 * ya cargados por el repositorio.
 */
final readonly class PokemonCadenaBase
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly int $speciesId,
    ) {
    }
}
