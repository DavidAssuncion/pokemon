<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\DataTransferObjects;

/**
 * Arista de evolución cargada por el repositorio.
 */
final readonly class EvolucionCadena
{
    public function __construct(
        public readonly int $evolvedSpeciesId,
        public readonly ?int $evolvesFromSpeciesId,
    ) {
    }
}
