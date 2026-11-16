<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Carbon\CarbonInterface;

/**
 * Deriva el estado de una expedición a partir de sus marcas temporales.
 *
 * Precedencia: cancelada > archivada > terminada (regreso marcado) >
 * pendiente_resolucion (fin <= ahora) > volviendo (inicio de vuelta <= ahora) >
 * explorando.
 */
final class CalculadorEstadoExpedicion
{
    public static function calcular(
        bool $archivada,
        bool $regresoMarcado,
        bool $cancelada,
        ?CarbonInterface $inicioVuelta,
        ?CarbonInterface $fin,
        CarbonInterface $ahora,
    ): EstadoExpedicion {
        if ($cancelada) {
            return EstadoExpedicion::CANCELADA;
        }

        if ($archivada) {
            return EstadoExpedicion::ARCHIVADA;
        }

        if ($regresoMarcado) {
            return EstadoExpedicion::TERMINADA;
        }

        if ($fin !== null && $fin->lessThanOrEqualTo($ahora)) {
            return EstadoExpedicion::PENDIENTE_RESOLUCION;
        }

        if ($inicioVuelta !== null && $inicioVuelta->lessThanOrEqualTo($ahora)) {
            return EstadoExpedicion::VOLVIENDO;
        }

        return EstadoExpedicion::EXPLORANDO;
    }
}
