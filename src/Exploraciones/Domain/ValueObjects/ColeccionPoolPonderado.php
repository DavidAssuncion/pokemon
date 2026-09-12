<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada del pool ponderado de un hábitat. La elección ponderada es
 * el ÚNICO modo de selección del negocio (peso = capture_rate/hatch): no existe
 * elección uniforme.
 */
final class ColeccionPoolPonderado extends Collection
{
    public string $type = PokemonPoolPonderado::class;

    /**
     * Frontera — lectura del contrato de pool ponderado.
     *
     * @param  list<array{id?: int, peso?: float}>  $pool
     */
    public static function desdeArray(array $pool): self
    {
        return new self(array_map(
            static fn (array $entrada): PokemonPoolPonderado => PokemonPoolPonderado::desdeArray($entrada),
            $pool,
        ));
    }

    /**
     * Frontera — shape exacto del contrato previo del pool ponderado.
     *
     * @return list<array{id: int, peso: float}>
     */
    public function aArrays(): array
    {
        return $this->map(static fn (PokemonPoolPonderado $entrada): array => $entrada->aArray());
    }

    /**
     * Suma de los pesos de todas las entradas.
     */
    public function pesoTotal(): float
    {
        return (float) $this->sum(static fn (PokemonPoolPonderado $entrada): float => $entrada->peso);
    }

    /**
     * Selección ponderada con aleatorio inyectable (float en [0, 1)).
     * Replica exacta de SimuladorEncuentros::elegirPonderado.
     *
     * @param  callable(): float  $aleatorio
     */
    public function elegirConAleatorio(callable $aleatorio): ?PokemonPoolPonderado
    {
        $total = $this->pesoTotal();
        if ($total <= 0) {
            return null;
        }

        $objetivo = $aleatorio() * $total;
        $acumulado = 0.0;
        $ultima = null;

        /** @var list<PokemonPoolPonderado> $items */
        $items = $this->items;

        foreach ($items as $entrada) {
            $ultima = $entrada;
            $acumulado += $entrada->peso;
            if ($acumulado > $objetivo) {
                return $entrada;
            }
        }

        return $ultima;
    }
}
