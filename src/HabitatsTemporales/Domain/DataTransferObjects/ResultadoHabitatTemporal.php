<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

/**
 * Resultado de registrar un combate de hábitat temporal.
 */
final readonly class ResultadoHabitatTemporal
{
    public function __construct(
        public bool $avance,
        public bool $completada,
    ) {
    }
}
