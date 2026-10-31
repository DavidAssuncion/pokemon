<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Pokemon\Domain\Stats\RangoIvs;

/**
 * Nido de una familia con su rango de IVs y estado de mejora.
 */
final readonly class NidoGranja
{
    public function __construct(
        public int $nivel,
        public RangoIvs $rangoIvs,
        public float $factorTiempo,
        public MejoraNido $mejora,
    ) {
    }

    public function reduccionTiempoPct(): int
    {
        return (int) round((1 - $this->factorTiempo) * 100);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nivel' => $this->nivel,
            'max' => $this->mejora->max,
            'rango_iv' => $this->rangoIvs->toArray(),
            'factor_tiempo' => $this->factorTiempo,
            'reduccion_tiempo_pct' => $this->reduccionTiempoPct(),
            'coste_siguiente' => $this->mejora->coste,
            'puede_mejorar' => $this->mejora->puede,
            'motivo' => $this->mejora->motivo,
        ];
    }
}
