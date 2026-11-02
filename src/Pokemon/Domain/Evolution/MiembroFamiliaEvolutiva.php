<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Evolution;

/**
 * Miembro resuelto de una familia evolutiva.
 *
 * `stage` es la profundidad respecto a la raíz de su componente (la raíz es 1);
 * `parentId` es el id del Pokémon padre (no el species id); `branchIndex` es la
 * posición entre hermanos ordenados por nivel mínimo, species id e id.
 */
final readonly class MiembroFamiliaEvolutiva
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly int $speciesId,
        public readonly int $stage,
        public readonly ?int $parentId,
        public readonly ?int $nivelMinimo,
        public readonly int $branchIndex,
    ) {
    }
}
