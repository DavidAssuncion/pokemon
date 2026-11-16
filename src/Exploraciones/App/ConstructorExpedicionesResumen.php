<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Src\Exploraciones\Domain\Collections\ExpedicionesResumenCollection;
use Src\Exploraciones\Domain\DataTransferObjects\ExpedicionResumen;
use Src\Exploraciones\Domain\EstadoExpedicion;
use Src\Exploraciones\Presentation\PresentadorExploraciones;

/**
 * Construye los DTOs compactos de expediciones a partir del presentador, para
 * no duplicar etiquetas, clases, progreso ni flags de acción.
 */
final class ConstructorExpedicionesResumen
{
    public function __construct(
        private readonly ConsultarExpedicionesUsuario $expediciones,
        private readonly PresentadorExploraciones $presentador,
    ) {
    }

    /**
     * Activas + terminadas pendientes de revisar, en orden canónico y sin
     * archivadas.
     */
    public function panel(int $userId): ExpedicionesResumenCollection
    {
        $grupos = $this->expediciones->para($userId);

        $resumenes = [];
        foreach ($grupos->activas as $expedicion) {
            $resumenes[] = $this->resumen($expedicion);
        }

        foreach ($grupos->terminadas as $expedicion) {
            $resumenes[] = $this->resumen($expedicion);
        }

        return new ExpedicionesResumenCollection($resumenes);
    }

    public function resumen(ExploracionActiva $exp): ExpedicionResumen
    {
        $vista = $this->presentador->toResumen($exp);

        $restanteMin = $vista['restante_min'];
        $restanteLabel = $vista['restante_label'];
        $inicioVuelta = $vista['inicio_vuelta'];
        $fin = $vista['fin'];

        return new ExpedicionResumen(
            id: (int) $vista['id'],
            reclutadoId: (int) $vista['reclutado_id'],
            pokemonId: (int) $vista['pokemon_id'],
            explorador: (string) $vista['explorador'],
            pokemon: (string) $vista['pokemon'],
            icono: (string) $vista['icono'],
            habitat: (string) $vista['habitat'],
            nivel: (int) $vista['nivel'],
            estado: EstadoExpedicion::from((string) $vista['estado']),
            estadoLabel: (string) $vista['estado_label'],
            estadoClase: (string) $vista['estado_clase'],
            progreso: (int) $vista['progreso'],
            restanteMin: $restanteMin === null ? null : (int) $restanteMin,
            restanteLabel: $restanteLabel === null ? null : (string) $restanteLabel,
            inicio: (string) $vista['inicio'],
            inicioVuelta: $inicioVuelta === null ? null : (string) $inicioVuelta,
            fin: $fin === null ? null : (string) $fin,
            indefinido: (bool) $vista['indefinido'],
            archivada: (bool) $vista['archivada'],
            puedeCancelar: (bool) $vista['puede_cancelar'],
            puedeRecoger: (bool) $vista['puede_recoger'],
            puedeCerrar: (bool) $vista['puede_cerrar'],
            puedeArchivar: (bool) $vista['puede_archivar'],
        );
    }
}
