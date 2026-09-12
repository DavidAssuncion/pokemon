<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de PokemonDelPool: el pool de encuentros de un hábitat
 * para el nivel de la exploración (RF-04). Sustituye los list<array> en la
 * generación de eventos y en la estimación de recompensas.
 */
final class PoolHabitat extends Collection
{
    public string $type = PokemonDelPool::class;

    /**
     * Frontera — lectura del contrato del pool.
     *
     * @param  list<array<string, mixed>>  $pool
     */
    public static function desdeArray(array $pool): self
    {
        return new self(array_map(
            static fn (array $pokemon): PokemonDelPool => PokemonDelPool::desdeArray($pokemon),
            $pool,
        ));
    }

    /**
     * Frontera — shape exacto del contrato previo del pool.
     *
     * @return list<array<string, mixed>>
     */
    public function aArrays(): array
    {
        return $this->map(static fn (PokemonDelPool $pokemon): array => $pokemon->aArray());
    }

    /**
     * Número de pokémon del pool.
     */
    public function tamano(): int
    {
        return $this->count();
    }

    /**
     * ¿El pool contiene un pokémon con el id dado?
     */
    public function contiene(int $id): bool
    {
        return $this->contains(static fn (PokemonDelPool $pokemon): bool => $pokemon->id === $id);
    }

    /**
     * Pool ponderado: peso = capture_rate / hatch (a mayor capture_rate más
     * probable, a mayor hatch menos probable). Los pokémon con peso <= 0
     * (capture_rate nulo) quedan excluidos. Replica exacta de
     * SimuladorEncuentros::poolPonderado.
     */
    public function ponderado(): ColeccionPoolPonderado
    {
        $entradas = [];

        /** @var list<PokemonDelPool> $items */
        $items = $this->items;

        foreach ($items as $pokemon) {
            $peso = $pokemon->peso();
            if ($peso <= 0) {
                continue;
            }

            $entradas[] = new PokemonPoolPonderado(
                id: $pokemon->id,
                peso: $peso,
            );
        }

        return new ColeccionPoolPonderado($entradas);
    }
}
