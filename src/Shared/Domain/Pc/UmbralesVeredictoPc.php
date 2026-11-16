<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Pc;

/**
 * Umbrales únicos del veredicto de PC.
 *
 * `ratio = pc_rival / pc_equipo`:
 * - `ratio < favorableMaximo` → favorable.
 * - `ratio > desfavorableMinimo` → desfavorable.
 * - en el resto → parejo.
 */
final readonly class UmbralesVeredictoPc
{
    public const FAVORABLE_MAXIMO = 0.8;

    public const DESFAVORABLE_MINIMO = 1.2;

    public function __construct(
        public float $favorableMaximo = self::FAVORABLE_MAXIMO,
        public float $desfavorableMinimo = self::DESFAVORABLE_MINIMO,
    ) {
    }
}
