<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\DataTransferObjects;

use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;

/**
 * Definición de un equipo inicial de onboarding.
 */
final readonly class EquipoDefinido
{
    public function __construct(
        public readonly string $key,
        public readonly string $nombre,
        public readonly IntCollection $pokemonIds,
        public readonly StringCollection $behaviors,
    ) {
    }
}
