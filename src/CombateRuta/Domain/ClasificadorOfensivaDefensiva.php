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
            $idx = $this->indiceMayorDefensiva($statsPorSlot);
            $posiciones[$idx] = Posicion::VANGUARDIA;
        } elseif ($numVan === $total) {
            // Todos VAN → mover al de mayor ofensiva (atk + speed) a RET
            $idx = $this->indiceMayorOfensiva($statsPorSlot);
            $posiciones[$idx] = Posicion::RETAGUARDIA;
        }

        return $posiciones;
    }

    /**
     * @param  list<DatosStats>  $statsPorSlot
     */
    private function indiceMayorOfensiva(array $statsPorSlot): int
    {
        $mejorIdx = 0;
        $mejorVal = 0;
        foreach ($statsPorSlot as $i => $stats) {
            $val = $stats->atk + $stats->speed;
            if ($val > $mejorVal) {
                $mejorVal = $val;
                $mejorIdx = $i;
            }
        }

        return $mejorIdx;
    }

    /**
     * @param  list<DatosStats>  $statsPorSlot
     */
    private function indiceMayorDefensiva(array $statsPorSlot): int
    {
        $mejorIdx = 0;
        $mejorVal = 0;
        foreach ($statsPorSlot as $i => $stats) {
            $val = $stats->def + $stats->spDef + $stats->hp;
            if ($val > $mejorVal) {
                $mejorVal = $val;
                $mejorIdx = $i;
            }
        }

        return $mejorIdx;
    }
}
