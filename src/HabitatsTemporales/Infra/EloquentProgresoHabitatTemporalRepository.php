<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Infra;

use App\Models\HabitatTemporalProgreso;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\HabitatsTemporales\Domain\Repositories\ProgresoHabitatTemporalRepositoryInterface;

/**
 * Persistencia Eloquent del progreso de hábitats temporales.
 */
final class EloquentProgresoHabitatTemporalRepository implements ProgresoHabitatTemporalRepositoryInterface
{
    public function obtener(int $userId, string $faccionSlug): ?ProgresoHabitatTemporal
    {
        $modelo = HabitatTemporalProgreso::query()
            ->where('user_id', $userId)
            ->where('faccion_slug', $faccionSlug)
            ->first();

        if ($modelo === null) {
            return null;
        }

        return new ProgresoHabitatTemporal(
            oleadaActual: $modelo->oleada_actual,
            combateActual: $modelo->combate_actual,
            completada: $modelo->completed_at !== null,
        );
    }

    public function registrarVictoria(
        int $userId,
        string $faccionSlug,
        int $oleadaCompletada,
        int $combateCompletado,
    ): void {
        $modelo = HabitatTemporalProgreso::query()
            ->where('user_id', $userId)
            ->where('faccion_slug', $faccionSlug)
            ->first();

        $oleadaActual = $modelo !== null ? $modelo->oleada_actual : 1;
        $combateActual = $modelo !== null ? $modelo->combate_actual : 1;
        $completada = $modelo !== null && $modelo->completed_at !== null;

        if ($completada
            || $oleadaCompletada !== $oleadaActual
            || $combateCompletado !== $combateActual
        ) {
            return;
        }

        $siguienteOleada = $oleadaActual;
        $siguienteCombate = $combateActual + 1;
        $nuevaCompletada = false;

        if ($siguienteCombate > ReglasHabitatTemporal::COMBATES_POR_OLEADA) {
            $siguienteCombate = 1;
            $siguienteOleada++;

            if ($siguienteOleada > ReglasHabitatTemporal::TOTAL_OLEADAS) {
                $siguienteOleada = ReglasHabitatTemporal::TOTAL_OLEADAS;
                $siguienteCombate = ReglasHabitatTemporal::COMBATES_POR_OLEADA;
                $nuevaCompletada = true;
            }
        }

        HabitatTemporalProgreso::updateOrCreate(
            [
                'user_id' => $userId,
                'faccion_slug' => $faccionSlug,
            ],
            [
                'oleada_actual' => $siguienteOleada,
                'combate_actual' => $siguienteCombate,
                'completed_at' => $nuevaCompletada ? now() : null,
            ],
        );
    }
}
