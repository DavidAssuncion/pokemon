<?php

declare(strict_types=1);

namespace App\Support\Pc;

use App\Models\Reclutado;
use Src\Pokemon\Domain\Stats\CalculadorCP;

/**
 * CP de un reclutado al nivel indicado, usando el CP canónico y los EVs/IVs
 * reales del reclutado. Devuelve null si la especie no tiene stats base.
 */
final class CalculadoraPcReclutado
{
    public static function calcular(Reclutado $reclutado, int $nivel): ?int
    {
        $base = EstadisticasBasePokemon::desdePokemon($reclutado->pokemon);

        if ($base === null) {
            return null;
        }

        return CalculadorCP::desdeStats($base, $reclutado->evs, $reclutado->ivs, $nivel);
    }
}
