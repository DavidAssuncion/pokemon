<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\DataTransferObjects;

use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;

/**
 * Equipo inicial con los nombres de sus pokémon resueltos para la vista.
 */
final readonly class EquipoInicialResumen
{
    public function __construct(
        public readonly string $key,
        public readonly string $nombre,
        public readonly IntCollection $pokemonIds,
        public readonly StringCollection $pokemonNombres,
    ) {
    }
}
