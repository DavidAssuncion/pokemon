<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

use Src\Shared\Collections\IntCollection;

/**
 * Equipo de una etapa de gimnasio (1-4) separado por posiciones:
 * vanguardia y retaguardia. Inmutable.
 *
 * Las colecciones contienen species_id enteros (IntCollection).
 */
final class EquipoEtapaGimnasio
{
    public function __construct(
        public readonly IntCollection $vanguardia,
        public readonly IntCollection $retaguardia,
    ) {
    }

    /** @return list<int> */
    public function todos(): array
    {
        return [...$this->vanguardia->all(), ...$this->retaguardia->all()];
    }
}
