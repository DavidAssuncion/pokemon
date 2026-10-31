<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Complemento EV de un stat con su estado de mejora.
 */
final readonly class ComplementoGranja
{
    public function __construct(
        public StatGranja $stat,
        public int $nivel,
        public int $ev,
        public MejoraComplemento $mejora,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stat' => $this->stat->stat->key(),
            'stat_id' => $this->stat->stat->value,
            'nombre' => $this->stat->nombre,
            'slug' => $this->stat->slug,
            'nivel' => $this->nivel,
            'max' => $this->mejora->max,
            'ev' => $this->ev,
            'coste_ev' => $this->mejora->coste?->ev,
            'coste_familia' => $this->mejora->coste?->familia,
            'puede_mejorar' => $this->mejora->puede,
            'motivo' => $this->mejora->motivo,
        ];
    }
}
