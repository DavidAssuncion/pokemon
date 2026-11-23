<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

/**
 * Progreso de un jugador en una facción: oleada y combate actuales.
 */
final readonly class ProgresoHabitatTemporal
{
    public function __construct(
        public int $oleadaActual,
        public int $combateActual,
        public bool $completada,
    ) {
    }

    public static function inicial(): self
    {
        return new self(oleadaActual: 1, combateActual: 1, completada: false);
    }
}
