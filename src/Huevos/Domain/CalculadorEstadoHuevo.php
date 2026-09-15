<?php

declare(strict_types=1);

namespace Src\Huevos\Domain;

use DateTimeInterface;

/**
 * Estado del huevo calculado on-read a partir de created_at + capture_rate.
 * No almacena estado; se deriva del timestamp de creación.
 */
final class CalculadorEstadoHuevo
{
    /**
     * @return array{estado: EstadoHuevo, restante_minutos: int}
     */
    public static function estadoDe(DateTimeInterface $creadoEn, int $captureRate, DateTimeInterface $ahora): array
    {
        $gestacionMin = CalculadorTiemposHuevo::gestacionMinutos($captureRate);
        $eclosionMin = CalculadorTiemposHuevo::eclosionMinutos($captureRate);

        $finGestacion = $creadoEn->getTimestamp() + $gestacionMin * 60;

        if ($ahora->getTimestamp() < $finGestacion) {
            return [
                'estado' => EstadoHuevo::GESTANDO,
                'restante_minutos' => (int) ceil(($finGestacion - $ahora->getTimestamp()) / 60),
            ];
        }

        $finIncubacion = $finGestacion + $eclosionMin * 60;

        if ($ahora->getTimestamp() < $finIncubacion) {
            return [
                'estado' => EstadoHuevo::INCUBANDO,
                'restante_minutos' => (int) ceil(($finIncubacion - $ahora->getTimestamp()) / 60),
            ];
        }

        return ['estado' => EstadoHuevo::LISTO, 'restante_minutos' => 0];
    }
}
