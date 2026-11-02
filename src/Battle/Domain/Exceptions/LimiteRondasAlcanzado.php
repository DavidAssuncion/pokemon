<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Exceptions;

/**
 * Se lanza cuando una batalla supera el tope de rondas configurado por el
 * llamador. No implica victoria ni derrota: la batalla queda truncada.
 */
final class LimiteRondasAlcanzado extends \RuntimeException
{
    public function __construct(
        public readonly int $maxRondas,
        public readonly int $rondasEjecutadas,
    ) {
        parent::__construct("Límite de {$maxRondas} rondas alcanzado");
    }
}
