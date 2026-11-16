<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

/**
 * Especie afectada por el avistamiento tal como se muestra en la página.
 */
final class EspecieAvistada
{
    public function __construct(
        public readonly int $pokemonId,
        public readonly string $nombre,
        public readonly bool $visto,
    ) {
    }
}
