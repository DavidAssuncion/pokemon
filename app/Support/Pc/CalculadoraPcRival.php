<?php

declare(strict_types=1);

namespace App\Support\Pc;

use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Pokemon\Domain\Stats\CalculadorCP;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * PC del rival. Reutiliza el CP canónico:
 * - equipos completos (entrenador/gimnasio): suma exacta;
 * - ruta: media ponderada por espécimen × slots.
 */
final class CalculadoraPcRival
{
    public function desdeDatosBatalla(
        DatosPokemonBatallaCollection $equipo,
        int $nivelFallback,
    ): ?int {
        if ($equipo->isEmpty()) {
            return null;
        }

        $total = 0;

        foreach ($equipo->all() as $datos) {
            $base = new StatsValue(
                hp: (float) $datos->hp,
                attack: (float) $datos->atk,
                defense: (float) $datos->def,
                spAtk: (float) $datos->spAtk,
                spDef: (float) $datos->spDef,
                speed: (float) $datos->speed,
            );

            $total += CalculadorCP::desdeStats(
                $base,
                $datos->evs,
                $datos->ivs,
                $datos->nivel ?? $nivelFallback,
            );
        }

        return $total;
    }

    /**
     * @param  list<MuestraPcRival>  $muestras
     */
    public function promedioPonderado(array $muestras, int $slots): ?int
    {
        if ($muestras === [] || $slots <= 0) {
            return null;
        }

        $sumaPesos = 0.0;
        $esperadoPorSlot = 0.0;

        foreach ($muestras as $muestra) {
            if ($muestra->peso <= 0) {
                continue;
            }

            $cp = CalculadorCP::desdeStats(
                $muestra->base,
                $muestra->evs,
                $muestra->ivs,
                $muestra->nivel,
            );

            $esperadoPorSlot += $cp * $muestra->peso;
            $sumaPesos += $muestra->peso;
        }

        if ($sumaPesos <= 0) {
            return null;
        }

        return (int) round(($esperadoPorSlot / $sumaPesos) * $slots);
    }
}
