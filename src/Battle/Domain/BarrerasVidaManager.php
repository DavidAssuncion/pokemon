<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

/**
 * Servicio de dominio puro que encapsula la lógica de vida y barreras.
 *
 * No guarda estado propio. Recibe valores actuales, devuelve resultados
 * para que el Combatiente aplique los cambios.
 */
final class BarrerasVidaManager
{
    public function estaVivo(float $hpActual): bool
    {
        return $hpActual > 0;
    }

    /**
     * Aplica daño desglosado: primero `directPct`% va directo a HP (ignora barreras),
     * el resto se descuenta de la barrera correspondiente (defensa física o especial).
     * El excedente de barrera se desborda al HP.
     *
     * @return array{hpNuevo: float, defensaHpNuevo: float, defensaEspHpNuevo: float}
     */
    public function recibirDaño(
        float $hpActual,
        float $defensaHpActual,
        float $defensaEspHpActual,
        float $daño,
        bool $isSpecial,
        float $directPct,
    ): array {
        $dañoDirecto = $daño * $directPct;
        $dañoBarreras = $daño - $dañoDirecto;

        $hpNuevo = $hpActual - $dañoDirecto;

        $barrera = $isSpecial ? $defensaEspHpActual : $defensaHpActual;
        $dañoBarrera = min($barrera, $dañoBarreras);

        if ($isSpecial) {
            $defensaEspHpNuevo = $defensaEspHpActual - $dañoBarrera;
            $defensaHpNuevo = $defensaHpActual;
        } else {
            $defensaHpNuevo = $defensaHpActual - $dañoBarrera;
            $defensaEspHpNuevo = $defensaEspHpActual;
        }

        $excedente = $dañoBarreras - $dañoBarrera;

        if ($excedente > 0) {
            $hpNuevo -= $excedente;
        }

        if ($hpNuevo < 0) {
            $hpNuevo = 0;
        }

        return [
            'hpNuevo' => $hpNuevo,
            'defensaHpNuevo' => $defensaHpNuevo,
            'defensaEspHpNuevo' => $defensaEspHpNuevo,
        ];
    }

    public function curarHp(float $hpActual, float $hpMax, float $porcentaje): float
    {
        return min($hpMax, $hpActual + $hpMax * $porcentaje / 100);
    }

    /**
     * @return array{defensaHpNuevo: float, defensaEspHpNuevo: float}
     */
    public function curarBarreras(
        float $defensaHpActual,
        float $defensaEspHpActual,
        float $defensaHpMax,
        float $defensaEspHpMax,
        float $porcentaje,
    ): array {
        return [
            'defensaHpNuevo' => min(
                $defensaHpMax,
                $defensaHpActual + $defensaHpMax * $porcentaje / 100
            ),
            'defensaEspHpNuevo' => min(
                $defensaEspHpMax,
                $defensaEspHpActual + $defensaEspHpMax * $porcentaje / 100
            ),
        ];
    }
}
