<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Collections\PlantacionesGranjaCollection;
use Src\Pokemon\Domain\Stats\RangoIvs;

/**
 * Nido de una familia con su rango de IVs, estado de mejora y plantaciones.
 */
final readonly class NidoGranja
{
    public readonly PlantacionesGranjaCollection $plantaciones;

    public function __construct(
        public int $nivel,
        public RangoIvs $rangoIvs,
        public float $factorTiempo,
        public MejoraNido $mejora,
        public int $huecosTotales = 0,
        ?PlantacionesGranjaCollection $plantaciones = null,
    ) {
        $this->plantaciones = $plantaciones ?? new PlantacionesGranjaCollection();
    }

    public function reduccionTiempoPct(): int
    {
        return (int) round((1 - $this->factorTiempo) * 100);
    }

    public function huecosLibres(): int
    {
        return max(0, $this->huecosTotales - count($this->plantaciones));
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
            'huecos_totales' => $this->huecosTotales,
            'huecos_libres' => $this->huecosLibres(),
            'plantaciones' => $this->plantaciones->toArray(),
        ];
    }
}
