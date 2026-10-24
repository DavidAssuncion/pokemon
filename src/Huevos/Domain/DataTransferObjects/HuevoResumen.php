<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

use Src\Shared\Domain\DataTransferObjects\PokemonResumen;

/**
 * Resumen de un huevo activo para la API/UI.
 *
 * Sustituye el array {id, estado, tiempo_total_horas, tiempo_restante,
 * progreso, puede_eclosionar, pokemon}.
 */
final readonly class HuevoResumen
{
    public function __construct(
        public readonly int $id,
        public readonly string $estado,
        public readonly float $tiempoTotalHoras,
        public readonly float $tiempoRestante,
        public readonly int $progreso,
        public readonly bool $puedeEclosionar,
        public readonly PokemonResumen $pokemon,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato JSON previo.
     *
     * @return array{
     *     id: int,
     *     estado: string,
     *     tiempo_total_horas: float,
     *     tiempo_restante: float,
     *     progreso: int,
     *     puede_eclosionar: bool,
     *     pokemon: array{id: int, name: string, icon: string},
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'tiempo_total_horas' => $this->tiempoTotalHoras,
            'tiempo_restante' => $this->tiempoRestante,
            'progreso' => $this->progreso,
            'puede_eclosionar' => $this->puedeEclosionar,
            'pokemon' => $this->pokemon->toArray(),
        ];
    }
}
