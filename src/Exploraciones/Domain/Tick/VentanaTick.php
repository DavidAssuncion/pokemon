<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tick;

use Carbon\CarbonInterface;

/**
 * Ventana temporal de un tick de exploración.
 *
 * Agrupa los límites calculados a partir del modelo y del último procesado:
 * inicio, fin, inicio de vuelta, desde (último procesado) y hasta (límite del
 * tick). Es un DTO de dominio puro; no conoce Eloquent ni la persistencia.
 */
final readonly class VentanaTick
{
    public function __construct(
        public CarbonInterface $inicio,
        public ?CarbonInterface $fin,
        public ?CarbonInterface $inicioVuelta,
        public CarbonInterface $desde,
        public CarbonInterface $hasta,
    ) {
    }

    /**
     * ¿El tick tiene tiempo nuevo que procesar?
     */
    public function procesable(): bool
    {
        return $this->hasta->greaterThan($this->desde);
    }
}
