<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain;

class ReclutadoEntity
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly int $pokemonId,
        public readonly string $pokemonName,
    ) {
    }
}
