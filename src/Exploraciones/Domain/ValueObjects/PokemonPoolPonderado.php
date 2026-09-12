<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

/**
 * Value Object inmutable con la entrada ponderada del pool de hábitat
 * (capture_rate / hatch). Alimenta la selección por peso del Simulador.
 */
final readonly class PokemonPoolPonderado
{
    public function __construct(
        public readonly int $id,
        public readonly float $peso,
    ) {
    }

    /**
     * Frontera — lectura del contrato de pool ponderado (list<array{id, peso}>).
     *
     * @param  array{id?: int, peso?: float}  $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            id: $datos['id'] ?? 0,
            peso: $datos['peso'] ?? 0,
        );
    }

    /**
     * Frontera — shape exacto del contrato previo del pool ponderado.
     *
     * @return array{id: int, peso: float}
     */
    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'peso' => $this->peso,
        ];
    }
}
