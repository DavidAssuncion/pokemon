<?php

declare(strict_types=1);

namespace Src\CombateRuta\Domain;

use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\Stats\DatosStats;

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
     * Genera formación determinista para un equipo (5 slots).
     * Mixed guarantee: si todos quedan en el mismo bando, mueve al de mayor
     * stat contraria al otro bando (el primero que lo cumple, para determinismo).
     *
     * @param  list<DatosStats>  $statsPorSlot
     * @return list<Posicion>
     */
    public function generarFormacion(array $statsPorSlot): array
    {
        $posiciones = array_map(
            fn (DatosStats $stats): Posicion => $this->esOfensivo($stats)
                ? Posicion::RETAGUARDIA
                : Posicion::VANGUARDIA,
            $statsPorSlot,
        );

        $total = count($posiciones);
        if ($total < 2) {
            return $posiciones;
        }

        $countVan = array_count_values(array_map(fn (Posicion $p): string => $p->value, $posiciones));
        $numVan = $countVan[Posicion::VANGUARDIA->value] ?? 0;

        if ($numVan === 0) {
            // Todos RET → mover al de mayor defensiva (def + spDef + hp) a VAN
            $posiciones[$this->indiceMayorValor(
                $statsPorSlot,
                static fn (DatosStats $stats): int => $stats->def + $stats->spDef + $stats->hp,
            )] = Posicion::VANGUARDIA;
        } elseif ($numVan === $total) {
            // Todos VAN → mover al de mayor ofensiva (atk + speed) a RET
            $posiciones[$this->indiceMayorValor(
                $statsPorSlot,
                static fn (DatosStats $stats): int => $stats->atk + $stats->speed,
            )] = Posicion::RETAGUARDIA;
        }

        return $posiciones;
    }

    /**
     * Índice del primer slot con el mayor valor (determinista en empates).
     *
     * @param  non-empty-list<DatosStats>  $statsPorSlot
     * @param  callable(DatosStats): int  $valor
     */
    private function indiceMayorValor(array $statsPorSlot, callable $valor): int
    {
        $valores = array_map($valor, $statsPorSlot);

        return (int) array_search(max($valores), $valores, true);
    }
}
