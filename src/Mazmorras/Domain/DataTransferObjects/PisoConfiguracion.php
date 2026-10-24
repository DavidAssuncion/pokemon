<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\DataTransferObjects;

/**
 * Configuración de un piso: número y species_id del jefe que lo habita.
 */
final readonly class PisoConfiguracion
{
    public function __construct(
        public readonly int $piso,
        public readonly int $speciesId,
    ) {
    }
}
