<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

/**
 * DTO que encapsula el resultado de calcular y aplicar daño.
 * Reemplaza el retorno de array ['dano' => float, 'directPct' => float].
 */
class DTOResultadoDanio
{
    public function __construct(
        public readonly float $dano,
        public readonly float $directPct,
    ) {
    }
}
