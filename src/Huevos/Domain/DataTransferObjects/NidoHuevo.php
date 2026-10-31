<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

use Src\Pokemon\Domain\Stats\RangoIvs;

/**
 * Nido asociado a un huevo (nivel, rango de IVs y factor de tiempo).
 */
final readonly class NidoHuevo
{
    public function __construct(
        public int $nivel,
        public RangoIvs $rangoIvs,
        public float $factorTiempo,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nivel' => $this->nivel,
            'rango_iv' => $this->rangoIvs->toArray(),
            'factor_tiempo' => $this->factorTiempo,
        ];
    }
}
