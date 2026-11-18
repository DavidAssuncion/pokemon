<?php

declare(strict_types=1);

namespace App\Support\Pc;

use App\Models\Reclutado;
use Src\Pokemon\Domain\Stats\AplicadorBonusPiezas;
use Src\Pokemon\Domain\Stats\CalculadorCP;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * CP de un reclutado al nivel indicado, usando el CP canónico y los EVs/IVs
 * reales del reclutado. Devuelve null si la especie no tiene stats base.
 *
 * Los bonos de las piezas equipadas se suman a los stats base antes del
 * cálculo, de modo que el CP coincida con los stats con los que combate.
 */
final class CalculadoraPcReclutado
{
    public static function calcular(
        Reclutado $reclutado,
        int $nivel,
        ?StatsValue $bonosPiezas = null,
    ): ?int {
        $base = EstadisticasBasePokemon::desdePokemon($reclutado->pokemon);

        if ($base === null) {
            return null;
        }

        $base = AplicadorBonusPiezas::aplicar($base, $bonosPiezas);

        return CalculadorCP::desdeStats($base, $reclutado->evs, $reclutado->ivs, $nivel);
    }
}
