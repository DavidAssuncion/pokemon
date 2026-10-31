<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Collections\ComplementosGranjaCollection;
use Src\Habitats\Presentation\DTOFamiliaDisponible;

/**
 * Familia del hábitat con sus infraestructuras de granja.
 */
final readonly class FamiliaGranja
{
    public function __construct(
        public DTOFamiliaDisponible $familia,
        public NidoGranja $nido,
        public RefugioGranja $refugio,
        public ComplementosGranjaCollection $complementos,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evolution_chain_id' => $this->familia->evolutionChainId,
            'base' => $this->familia->base->toArray(),
            'evolutions' => $this->familia->evolutions->toArray(),
            'types' => $this->familia->types->toArray(),
            'nido' => $this->nido->toArray(),
            'refugio' => $this->refugio->toArray(),
            'complementos' => $this->complementos->toArray(),
            'ev_total' => $this->complementos->evTotal(),
        ];
    }
}
