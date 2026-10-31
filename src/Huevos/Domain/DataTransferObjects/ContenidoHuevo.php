<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

use Src\Shared\Domain\DataTransferObjects\PokemonResumen;

/**
 * Contenido de un huevo: pokémon y nido con el que se puso.
 */
final readonly class ContenidoHuevo
{
    public function __construct(
        public PokemonResumen $pokemon,
        public NidoHuevo $nido,
    ) {
    }
}
