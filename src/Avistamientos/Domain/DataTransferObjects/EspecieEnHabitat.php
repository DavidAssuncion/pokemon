<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

/**
 * Especie presente en el pool de un hábitat, con su nivel de aparición.
 */
final class EspecieEnHabitat
{
    public function __construct(
        public readonly int $pokemonId,
        public readonly string $nombre,
        public readonly int $speciesId,
        public readonly int $nivel,
    ) {
    }
}
