<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain;

use Src\CombateRuta\Domain\ClasificadorOfensivaDefensiva;
use Src\Pokemon\Domain\Stats\DatosStats;

/**
 * Clasifica un pokémon como defensivo (vanguardia) u ofensivo (retaguardia).
 *
 * @deprecated Usar ClasificadorOfensivaDefensiva (nova fórmula:
 *             ofensiva = atk + speed; defensiva = def + spDef + hp).
 */
class ClasificadorPosicion
{
    public function __construct(
        private readonly ClasificadorOfensivaDefensiva $clasificador = new ClasificadorOfensivaDefensiva(),
    ) {
    }

    public function esDefensivo(DatosStats $stats): bool
    {
        return ! $this->clasificador->esOfensivo($stats);
    }
}
