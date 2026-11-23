<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use Src\HabitatsTemporales\Domain\CatalogoFaccionesRivales;
use Src\HabitatsTemporales\Domain\Collections\CombateVistaCollection;
use Src\HabitatsTemporales\Domain\CombateRival;
use Src\HabitatsTemporales\Domain\DataTransferObjects\CombateVista;
use Src\HabitatsTemporales\Domain\DataTransferObjects\FaccionDetalle;
use Src\HabitatsTemporales\Domain\DataTransferObjects\OleadaActualVista;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;
use Src\HabitatsTemporales\Domain\Enums\EstadoCombate;
use Src\HabitatsTemporales\Domain\Enums\EstadoOleada;
use Src\HabitatsTemporales\Domain\Exceptions\FaccionNoExiste;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\HabitatsTemporales\Domain\Repositories\MedallasRepositoryInterface;
use Src\HabitatsTemporales\Domain\Repositories\ProgresoHabitatTemporalRepositoryInterface;

/**
 * Detalle de una facción con la oleada actual completa (4 combates) y el
 * combate actual. Las oleadas y los entrenadores futuros no se calculan.
 */
final class ObtenerDetalleFaccion
{
    public function __construct(
        private readonly CatalogoFaccionesRivales $catalogo,
        private readonly ProgresoHabitatTemporalRepositoryInterface $progresos,
        private readonly MedallasRepositoryInterface $medallas,
        private readonly ResolutorAssetsFaccion $assets,
        private readonly EvaluadorEstadoFaccion $evaluador,
    ) {
    }

    public function obtener(string $slug, int $userId): FaccionDetalle
    {
        $faccion = $this->catalogo->porSlug($slug);
        $medallas = $this->medallas->contarCompletadas($userId);

        if ($medallas < $faccion->medallasDesbloqueo) {
            throw new FaccionNoExiste($slug);
        }

        $progreso = $this->progresos->obtener($userId, $slug)
            ?? ProgresoHabitatTemporal::inicial();

        if ($progreso->completada) {
            throw new FaccionNoExiste($slug);
        }

        $motivo = $this->evaluador->motivoBloqueoOleada($progreso->oleadaActual, $medallas);

        if ($motivo !== null) {
            throw new FaccionNoExiste($slug);
        }

        $estadoOleada = EstadoOleada::Actual;

        $combates = new CombateVistaCollection();
        $combateActual = null;
        $oleadaRival = $faccion->oleada($progreso->oleadaActual);
        $combatesRival = $oleadaRival?->combates->toList() ?? [];

        foreach ($combatesRival as $combateRival) {
            if (! $combateRival instanceof CombateRival) {
                continue;
            }

            $vista = $this->construirCombate($combateRival, $progreso->combateActual, $motivo);
            $combates->add($vista);

            if ($combateRival->numero === $progreso->combateActual) {
                $combateActual = $vista;
            }
        }

        return new FaccionDetalle(
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
            oleada: new OleadaActualVista(
                numero: $progreso->oleadaActual,
                estado: $estadoOleada,
                motivoBloqueo: $motivo,
            ),
            combate: $combateActual,
            combates: $combates,
        );
    }

    private function construirCombate(
        CombateRival $combate,
        int $combateActual,
        ?string $motivoOleada,
    ): CombateVista {
        if ($combate->numero < $combateActual) {
            $estado = EstadoCombate::Superado;
            $motivo = null;
        } elseif ($combate->numero === $combateActual) {
            $estado = $motivoOleada === null ? EstadoCombate::Disponible : EstadoCombate::Bloqueado;
            $motivo = $motivoOleada;
        } else {
            $estado = EstadoCombate::Bloqueado;
            $motivo = $motivoOleada ?? 'Completa el combate anterior.';
        }

        return new CombateVista(
            numero: $combate->numero,
            nombre: $combate->nombre,
            entrenador: $combate->entrenador,
            descripcion: $combate->descripcion,
            spriteUrl: $this->assets->spriteUrl($combate->sprite),
            esJefe: $combate->esJefe,
            estado: $estado,
            motivoBloqueo: $motivo,
        );
    }
}
