<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\ValueObjects\ResultadoDanio;

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
     * `$multiplicadorEscudo` solo escala la parte que intenta absorber la
     * barrera; `$multiplicadorSalud` escala el daño directo y el excedente que
     * llega a HP.
     */
    public function desglosarDaño(
        float $daño,
        float $barreraActual,
        float $directPct,
        float $multiplicadorEscudo = 1.0,
        float $multiplicadorSalud = 1.0,
    ): ResultadoDanio {
        $dañoDirecto = $daño * $directPct;
        $dañoBarrera = $daño * (1 - $directPct) * $multiplicadorEscudo;
        $absorbido = min($barreraActual, $dañoBarrera);
        $excedente = max(0.0, $dañoBarrera - $absorbido);
        $dañoHp = ($dañoDirecto + $excedente) * $multiplicadorSalud;

        return new ResultadoDanio(
            danoHp: $dañoHp,
            danoEscudo: $absorbido,
            excedente: $excedente,
            rompioEscudo: $barreraActual > 0.0 && $absorbido >= $barreraActual && $dañoBarrera > 0.0,
        );
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
