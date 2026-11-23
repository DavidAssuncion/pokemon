<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ResultadoHabitatTemporal;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\HabitatsTemporales\Domain\Repositories\ProgresoHabitatTemporalRepositoryInterface;

/**
 * Persiste el resultado de un combate de hábitat temporal.
 *
 * Solo avanza si el combate ganado es el actual y el usuario autenticado
 * coincide con el dueño de la batalla. Sin cooldown por derrota.
 */
final class RegistrarResultadoHabitatTemporal
{
    public function __construct(
        private readonly ProgresoHabitatTemporalRepositoryInterface $progresos,
    ) {
    }

    public function registrar(
        string $faccionSlug,
        int $oleada,
        int $combate,
        int $userId,
        bool $won,
        int $authUserId,
    ): ResultadoHabitatTemporal {
        if (! $won || $userId !== $authUserId) {
            return new ResultadoHabitatTemporal(avance: false, completada: false);
        }

        $progreso = $this->progresos->obtener($userId, $faccionSlug)
            ?? ProgresoHabitatTemporal::inicial();

        if ($progreso->completada
            || $oleada !== $progreso->oleadaActual
            || $combate !== $progreso->combateActual
        ) {
            return new ResultadoHabitatTemporal(avance: false, completada: false);
        }

        $this->progresos->registrarVictoria($userId, $faccionSlug, $oleada, $combate);

        $completada = $oleada === ReglasHabitatTemporal::TOTAL_OLEADAS
            && $combate === ReglasHabitatTemporal::COMBATES_POR_OLEADA;

        return new ResultadoHabitatTemporal(avance: true, completada: $completada);
    }
}
