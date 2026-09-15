<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

use DateTimeInterface;

/**
 * Estado del huevo calculado on-read a partir de created_at + capture_rate + hatch.
 * No almacena estado; se deriva del timestamp de creación.
 */
final class CalculadorEstadoHuevo
{
    /**
     * @return array{estado: EstadoHuevo, restante_horas: float}
     */
    public static function estadoDe(
        DateTimeInterface $creadoEn,
        int $captureRate,
        int $hatch,
        DateTimeInterface $ahora,
    ): array {
        $puestaHoras = CalculadorTiemposHuevo::puestaHoras($captureRate);
        $eclosionHoras = CalculadorTiemposHuevo::eclosionHoras($hatch);

        $finGestacion = $creadoEn->getTimestamp() + (int) round($puestaHoras * 3600);

        if ($ahora->getTimestamp() < $finGestacion) {
            return [
                'estado' => EstadoHuevo::GESTANDO,
                // Redondeo a 2 decimales: el frontend formatea el resto con
                // floor(h) + round(min) y 84.9999∞ mostraría "84h 60m".
                'restante_horas' => round(($finGestacion - $ahora->getTimestamp()) / 3600, 2),
            ];
        }

        $finIncubacion = $finGestacion + (int) round($eclosionHoras * 3600);

        if ($ahora->getTimestamp() < $finIncubacion) {
            return [
                'estado' => EstadoHuevo::INCUBANDO,
                'restante_horas' => round(($finIncubacion - $ahora->getTimestamp()) / 3600, 2),
            ];
        }

        return ['estado' => EstadoHuevo::LISTO, 'restante_horas' => 0.0];
    }
}
