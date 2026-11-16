<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

/**
 * Estado de «visto» de una especie para el jugador que consulta.
 */
final class EspecieVista
{
    public function __construct(
        public readonly int $pokemonId,
        public readonly bool $visto,
    ) {
    }
}
