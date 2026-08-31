<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain;

/**
 * Clasifica un pokémon como defensivo (vanguardia) u ofensivo (retaguardia)
 * según la comparación de sus stats ofensivos vs defensivos.
 *
 * Defensivo → Vanguardia   (def + spDef >= atk + spAtk)
 * Ofensivo  → Retaguardia  (atk + spAtk > def + spDef)
 */
class ClasificadorPosicion
{
    /**
     * @param  array{atk: int, spAtk: int, def: int, spDef: int}  $stats
     */
    public function esDefensivo(array $stats): bool
    {
        $ofensivo = $stats['atk'] + $stats['spAtk'];
        $defensivo = $stats['def'] + $stats['spDef'];

        return $defensivo >= $ofensivo;
    }
}
