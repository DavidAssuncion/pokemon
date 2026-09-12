<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de eventos de la bitácora de exploración. Sustituye los
 * `list<array<string, mixed>>` en la resolución y en el cálculo de recompensas.
 */
final class ColeccionEventosExploracion extends Collection
{
    public string $type = EventoExploracion::class;

    /**
     * Frontera — lectura del contrato persistido de una bitácora.
     *
     * @param  list<array<string, mixed>>  $eventos
     */
    public static function desdeArray(array $eventos): self
    {
        return new self(array_map(
            static fn (array $evento): EventoExploracion => EventoExploracion::desdeArray($evento),
            $eventos,
        ));
    }

    /**
     * Frontera — shape exacto del contrato previo de la bitácora.
     *
     * @return list<array<string, mixed>>
     */
    public function aArrays(): array
    {
        return $this->map(static fn (EventoExploracion $evento): array => $evento->aArray());
    }

    /**
     * Evento de la posición indicada o null si no existe.
     */
    public function item(int $indice): ?EventoExploracion
    {
        /** @var list<EventoExploracion> $items */
        $items = $this->items;

        return $items[$indice] ?? null;
    }

    /**
     * Número de eventos de la bitácora.
     */
    public function tamano(): int
    {
        return $this->count();
    }
}
