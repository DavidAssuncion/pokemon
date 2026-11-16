<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\ValueObjects;

/**
 * Hábitat elegible para el avistamiento de una ventana.
 */
final class HabitatCandidato
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
    ) {
    }
}
