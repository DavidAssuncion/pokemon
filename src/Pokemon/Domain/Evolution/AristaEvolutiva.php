<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Evolution;

/**
 * Arista de evolución entre dos especies.
 *
 * `desdeSpeciesId` es la especie pre-evolucionada y `haciaSpeciesId` la
 * evolución resultante. `nivelMinimo` es el nivel mínimo de la evolución, si
 * la arista lo declara.
 */
final readonly class AristaEvolutiva
{
    public function __construct(
        public readonly int $desdeSpeciesId,
        public readonly int $haciaSpeciesId,
        public readonly ?int $nivelMinimo,
    ) {
    }
}
