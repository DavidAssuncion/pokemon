<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Evento emitido al cruzar el umbral de una fase de jefe.
 */
final readonly class EventoFaseJefe
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $nombre,
        public readonly float $bonificadorDano,
        public readonly float $bonificadorVelocidad,
        public readonly bool $enrage,
    ) {
    }
}
