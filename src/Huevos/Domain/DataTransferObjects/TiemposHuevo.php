<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

/**
 * Tiempos y progreso de un huevo para la API.
 */
final readonly class TiemposHuevo
{
    public function __construct(
        public float $totalHoras,
        public float $restanteHoras,
        public int $progreso,
        public bool $puedeEclosionar,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tiempo_total_horas' => $this->totalHoras,
            'tiempo_restante' => $this->restanteHoras,
            'progreso' => $this->progreso,
            'puede_eclosionar' => $this->puedeEclosionar,
        ];
    }
}
