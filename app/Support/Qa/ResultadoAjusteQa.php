<?php

declare(strict_types=1);

namespace App\Support\Qa;

/**
 * Resultado de una mutación QA: valor previo, valor final y delta realmente
 * aplicado (con signo, puede ser menor que el solicitado por el clamp).
 */
final readonly class ResultadoAjusteQa
{
    public function __construct(
        public string $clave,
        public int $anterior,
        public int $actual,
        public int $aplicado,
    ) {
    }
}
