<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI\ValueObjects;

/**
 * Estimación de daño de un movimiento contra un objetivo.
 *
 * - `minimo`/`maximo`/`esperado`: daño bruto calculado por la cadena de daño.
 * - `hpDamage`: daño efectivo que recibe el HP real del objetivo, descontando
 *   la barrera física/especial relevante y considerando el daño directo (perforación).
 *   Es la métrica que realmente acerca al KO, porque el KO ocurre cuando el HP llega a 0.
 * - `probabilidadKO`: binaria; 1.0 si `hpDamage >= hpActual` (KO real), 0.0 en caso contrario.
 */
final readonly class EstimacionDanio
{
    public function __construct(
        public float $minimo,
        public float $maximo,
        public float $esperado,
        public float $probabilidadKO,
        public float $hpDamage = 0.0,
    ) {
    }
}
