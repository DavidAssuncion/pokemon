<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Rango de IVs de un rival: valor para el stat principal y para el resto.
 *
 * Sustituye el array posicional `[ivPrincipal, ivResto]`.
 */
final readonly class RangoIvs
{
    public function __construct(
        public readonly int $principal,
        public readonly int $resto,
    ) {
    }
}
