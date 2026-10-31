<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

use DateTimeInterface;
use Src\Huevos\Domain\DataTransferObjects\DatosTiempoHuevo;

/**
 * Estado del huevo calculado on-read a partir de created_at + capture_rate + hatch.
 * No almacena estado; se deriva del timestamp de creación.
 */
final class CalculadorEstadoHuevo
{
    public static function estadoDe(
        DateTimeInterface $creadoEn,
        DatosTiempoHuevo $datos,
        DateTimeInterface $ahora,
    ): EstadoCalculadoHuevo {
        $puestaHoras = CalculadorTiemposHuevo::puestaHoras($datos->captureRate, $datos->nidoNivel);
        $eclosionHoras = CalculadorTiemposHuevo::eclosionHoras($datos->hatch, $datos->nidoNivel);

        $finGestacion = $creadoEn->getTimestamp() + (int) round($puestaHoras * 3600);

        if ($ahora->getTimestamp() < $finGestacion) {
            return new EstadoCalculadoHuevo(
                estado: EstadoHuevo::GESTANDO,
                // Redondeo a 2 decimales: el frontend formatea el resto con
                // floor(h) + round(min) y 84.9999∞ mostraría "84h 60m".
                restanteHoras: round(($finGestacion - $ahora->getTimestamp()) / 3600, 2),
            );
        }

        $finIncubacion = $finGestacion + (int) round($eclosionHoras * 3600);

        if ($ahora->getTimestamp() < $finIncubacion) {
            return new EstadoCalculadoHuevo(
                estado: EstadoHuevo::INCUBANDO,
                restanteHoras: round(($finIncubacion - $ahora->getTimestamp()) / 3600, 2),
            );
        }

        return new EstadoCalculadoHuevo(estado: EstadoHuevo::LISTO, restanteHoras: 0.0);
    }
}
