<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use Src\HabitatsTemporales\Domain\CatalogoFaccionesRivales;
use Src\HabitatsTemporales\Domain\Collections\FaccionResumenCollection;
use Src\HabitatsTemporales\Domain\DataTransferObjects\FaccionResumen;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ProvinciaTemporal;
use Src\HabitatsTemporales\Domain\FaccionRival;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\HabitatsTemporales\Domain\Repositories\MedallasRepositoryInterface;
use Src\HabitatsTemporales\Domain\Repositories\ProgresoHabitatTemporalRepositoryInterface;

/**
 * Compone la provincia virtual «Temporal» con las facciones desbloqueadas
 * y pendientes de completar.
 */
final class ObtenerProvinciaTemporal
{
    public function __construct(
        private readonly CatalogoFaccionesRivales $catalogo,
        private readonly ProgresoHabitatTemporalRepositoryInterface $progresos,
        private readonly MedallasRepositoryInterface $medallas,
        private readonly ResolutorAssetsFaccion $assets,
        private readonly EvaluadorEstadoFaccion $evaluador,
    ) {
    }

    public function obtener(int $userId): ?ProvinciaTemporal
    {
        $medallas = $this->medallas->contarCompletadas($userId);
        $facciones = new FaccionResumenCollection();

        foreach ($this->catalogo->todas() as $faccion) {
            if (! $faccion instanceof FaccionRival || $medallas < $faccion->medallasDesbloqueo) {
                continue;
            }

            $progreso = $this->progresos->obtener($userId, $faccion->slug)
                ?? ProgresoHabitatTemporal::inicial();

            if ($progreso->completada) {
                continue;
            }

            $motivo = $this->evaluador->motivoBloqueoOleada($progreso->oleadaActual, $medallas);

            if ($motivo !== null) {
                continue;
            }

            $facciones->add(new FaccionResumen(
                slug: $faccion->slug,
                nombre: $faccion->nombre,
                descripcion: $faccion->descripcion,
                imagenUrl: $this->assets->imagenUrl($faccion->imagen),
                color: $faccion->color,
                oleadaActual: $progreso->oleadaActual,
                combateActual: $progreso->combateActual,
                totalOleadas: $faccion->totalOleadas(),
                combatesPorOleada: ReglasHabitatTemporal::COMBATES_POR_OLEADA,
                estado: $this->evaluador->estadoFaccion($progreso->oleadaActual, $medallas),
                motivoBloqueo: $motivo,
                enlace: $this->assets->enlace($faccion->slug),
            ));
        }

        if ($facciones->isEmpty()) {
            return null;
        }

        return new ProvinciaTemporal(
            nombre: 'Temporal',
            descripcion: 'Hábitats de equipos rivales. Desaparecen al completarlos.',
            facciones: $facciones,
        );
    }
}
