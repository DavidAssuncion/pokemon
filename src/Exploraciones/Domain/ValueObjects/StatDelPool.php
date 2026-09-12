<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

/**
 * Value Object inmutable con la estadística de un pokémon del pool de hábitat
 * (las stats con effort > 0 alimentan los caramelos EV de los hallazgos).
 */
final readonly class StatDelPool
{
    public function __construct(
        public readonly int $stat,
        public readonly int $effort,
    ) {
    }

    /**
     * Frontera — lectura del contrato del pool (list<array{stat:int, effort:int}>).
     *
     * @param  array{stat?: int, effort?: int}  $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            stat: $datos['stat'] ?? 0,
            effort: $datos['effort'] ?? 0,
        );
    }

    /**
     * Frontera — shape exacto del contrato previo del pool.
     *
     * @return array{stat: int, effort: int}
     */
    public function aArray(): array
    {
        return [
            'stat' => $this->stat,
            'effort' => $this->effort,
        ];
    }
}
