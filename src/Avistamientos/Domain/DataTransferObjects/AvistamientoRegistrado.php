<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

use DateTimeImmutable;

/**
 * Fila persistida de una ventana de avistamiento.
 *
 * Se congela al crear la ventana para que un cambio del pool no altere la
 * selección mientras el evento está activo.
 */
final class AvistamientoRegistrado
{
    public function __construct(
        public readonly int $windowIndex,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public readonly int $habitatId,
        public readonly int $evolutionChainId,
        public readonly float $factor,
    ) {
    }
}
