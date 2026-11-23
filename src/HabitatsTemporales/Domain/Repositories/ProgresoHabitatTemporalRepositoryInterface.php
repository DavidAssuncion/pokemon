<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Repositories;

use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;

/**
 * Persistencia del progreso de un jugador en una facción temporal.
 */
interface ProgresoHabitatTemporalRepositoryInterface
{
    public function obtener(int $userId, string $faccionSlug): ?ProgresoHabitatTemporal;

    public function registrarVictoria(
        int $userId,
        string $faccionSlug,
        int $oleadaCompletada,
        int $combateCompletado,
    ): void;
}
