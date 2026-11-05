<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos;

/**
 * Resultado de aplicar un efecto concreto de un objeto.
 */
final readonly class ResultadoEfectoItem
{
    public function __construct(
        public string $texto,
        public int $curado = 0,
        public bool $revivido = false,
    ) {
    }
}
