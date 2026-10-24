<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\DatosStatsCollection;
use Src\Shared\Domain\Collections\PosicionCollection;

/**
 * Clasifica un pokémon como ofensivo (retaguardia) o defensivo (vanguardia)
 * según la comparación de sus stats: ofensiva = atk + speed; defensiva = def + spDef + hp.
 *
 * Genera formación determinista (sin random_int) con mixed guarantee:
 * si todos quedan en el mismo bando, mueve al de mayor stat contraria al otro.
 */
final class ClasificadorOfensivaDefensiva
{
    /**
     * Ofensivo si (atk + speed) > (def + spDef + hp). Empate → defensivo.
     */
    public function esOfensivo(DatosStats $stats): bool
    {
        $ofensiva = $stats->atk + $stats->speed;
        $defensiva = $stats->def + $stats->spDef + $stats->hp;

        return $ofensiva > $defensiva;
    }

    /**
     * Genera formación determinista para un equipo (vanguardia/retaguardia,
     * independiente del número de slots).
     * Mixed guarantee: si todos quedan en el mismo bando, mueve al de mayor
     * stat contraria al otro bando (el primero que lo cumple, para determinismo).
     *
     * @param  DatosStatsCollection  $statsPorSlot  stats en el orden de los slots
     */
    public function generarFormacion(DatosStatsCollection $statsPorSlot): PosicionCollection
    {
        $posiciones = new PosicionCollection(array_map(
            fn (DatosStats $stats): Posicion => $this->esOfensivo($stats)
                ? Posicion::RETAGUARDIA
                : Posicion::VANGUARDIA,
            $statsPorSlot->all(),
        ));

        $total = $posiciones->count();
        if ($total < 2) {
            return $posiciones;
        }

        $numVan = $posiciones->cantidad(Posicion::VANGUARDIA);

        if ($numVan === 0) {
            // Todos RET → mover al de mayor defensiva (def + spDef + hp) a VAN
            $posiciones->reemplazar($this->indiceMayorValor(
                $statsPorSlot,
                static fn (DatosStats $stats): int => $stats->def + $stats->spDef + $stats->hp,
            ), Posicion::VANGUARDIA);
        } elseif ($numVan === $total) {
            // Todos VAN → mover al de mayor ofensiva (atk + speed) a RET
            $posiciones->reemplazar($this->indiceMayorValor(
                $statsPorSlot,
                static fn (DatosStats $stats): int => $stats->atk + $stats->speed,
            ), Posicion::RETAGUARDIA);
        }

        return $posiciones;
    }

    /**
     * Índice del primer slot con el mayor valor (determinista en empates).
     *
     * @param  callable(DatosStats): int  $valor
     */
    private function indiceMayorValor(DatosStatsCollection $statsPorSlot, callable $valor): int
    {
        $valores = array_map($valor, $statsPorSlot->all());

        return (int) array_search(max($valores), $valores, true);
    }
}
