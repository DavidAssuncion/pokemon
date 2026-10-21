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
     * Desglose puro del daño: parte absorbida por la barrera y parte que llega
     * a HP (daño directo + excedente de la barrera).
     *
     * @return array{danoHp: float, absorbido: float, excedente: float}
     */
    public function desglosarDaño(float $daño, float $barreraActual, float $directPct): array
    {
        $dañoDirecto = $daño * $directPct;
        $dañoBarreras = $daño - $dañoDirecto;
        $absorbido = min($barreraActual, $dañoBarreras);
        $excedente = $dañoBarreras - $absorbido;

        return [
            'danoHp' => $dañoDirecto + $excedente,
            'absorbido' => $absorbido,
            'excedente' => $excedente,
        ];
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
        $barrera = $isSpecial ? $defensaEspHpActual : $defensaHpActual;
        $desglose = $this->desglosarDaño($daño, $barrera, $directPct);

        $hpNuevo = $hpActual - $desglose['danoHp'];
        if ($hpNuevo < 0) {
            $hpNuevo = 0;
        }

        if ($isSpecial) {
            $defensaEspHpNuevo = $defensaEspHpActual - $desglose['absorbido'];
            $defensaHpNuevo = $defensaHpActual;
        } else {
            $defensaHpNuevo = $defensaHpActual - $desglose['absorbido'];
            $defensaEspHpNuevo = $defensaEspHpActual;
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
