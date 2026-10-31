<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Refugio de una familia con su efecto y estado de mejora.
 */
final readonly class RefugioGranja
{
    public function __construct(
        public int $nivel,
        public bool $insalubres,
        public EfectoRefugio $efecto,
        public MejoraRefugio $mejora,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nivel' => $this->nivel,
            'max' => $this->mejora->max,
            'insalubres' => $this->insalubres,
            'multiplicador_aparicion' => $this->efecto->factorAparicion,
            'multiplicador_captura' => $this->efecto->factorCaptura,
            'excluido' => $this->efecto->excluido,
            'exclusivo' => $this->efecto->exclusivo,
            'coste_siguiente' => $this->mejora->coste,
            'puede_mejorar' => $this->mejora->puede,
            'motivo' => $this->mejora->motivo,
        ];
    }
}
