<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

/**
 * Parámetros de cadencia calculados al inicio de un tick.
 */
final readonly class ParametrosGeneracionTick
{
    public function __construct(
        public int $tiempoPerdido,
        public int $intervaloBase,
        public float $reduccionIntervalo,
        public int $bonusEventos,
        public int $eventosGenerados,
    ) {
    }
}
