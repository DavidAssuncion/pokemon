<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

/**
 * Resumen de un huevo activo para la API/UI.
 */
final readonly class HuevoResumen
{
    public function __construct(
        public int $id,
        public string $estado,
        public TiemposHuevo $tiempos,
        public ContenidoHuevo $contenido,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'tiempo_total_horas' => $this->tiempos->totalHoras,
            'tiempo_restante' => $this->tiempos->restanteHoras,
            'progreso' => $this->tiempos->progreso,
            'puede_eclosionar' => $this->tiempos->puedeEclosionar,
            'pokemon' => $this->contenido->pokemon->toArray(),
            'nido_nivel' => $this->contenido->nido->nivel,
            'rango_iv' => $this->contenido->nido->rangoIvs->toArray(),
            'factor_tiempo' => $this->contenido->nido->factorTiempo,
        ];
    }
}
