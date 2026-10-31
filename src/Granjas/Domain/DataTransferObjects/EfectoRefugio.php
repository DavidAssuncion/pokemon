<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Efecto de aparición/captura de un refugio.
 */
final readonly class EfectoRefugio
{
    public function __construct(
        public float $factorAparicion,
        public float $factorCaptura,
        public bool $excluido,
        public bool $exclusivo,
    ) {
    }
}
