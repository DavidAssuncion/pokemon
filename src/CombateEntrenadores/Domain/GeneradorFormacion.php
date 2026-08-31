<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain;

/**
 * Genera la formación 1/2 o 2/1 (vanguardia/retaguardia) para un equipo de 3.
 *
 * Respeta la clasificación: los pokémon defensivos tienen prioridad para
 * ocupar las plazas de vanguardia; los ofensivos para retaguardia.
 * La formación (cantidad de vanguardia) se elige aleatoriamente.
 */
class GeneradorFormacion
{
    /**
     * @param  bool[]  $esDefensivoPorIndice  ordenado por índice del miembro
     * @return string[]  'vanguardia'|'retaguardia' por índice
     */
    public function generar(array $esDefensivoPorIndice): array
    {
        $total = count($esDefensivoPorIndice);

        $vanguardiaCount = random_int(1, max(1, $total - 1));

        $indicesDefensivos = [];
        $indicesOfensivos = [];
        foreach ($esDefensivoPorIndice as $i => $esDef) {
            if ($esDef) {
                $indicesDefensivos[] = $i;
            } else {
                $indicesOfensivos[] = $i;
            }
        }

        $vanguardia = array_slice(array_merge($indicesDefensivos, $indicesOfensivos), 0, $vanguardiaCount);

        $posiciones = array_fill(0, $total, 'retaguardia');
        foreach ($vanguardia as $i) {
            $posiciones[$i] = 'vanguardia';
        }

        return $posiciones;
    }
}
