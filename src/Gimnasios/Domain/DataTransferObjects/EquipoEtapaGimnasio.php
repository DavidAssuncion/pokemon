<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

use Src\Shared\Domain\Collection;

/**
 * Equipo de una etapa de gimnasio (1-4) separado por posiciones:
 * vanguardia y retaguardia. Inmutable.
 *
 * Las colecciones contienen species_id enteros (IntCollection); se tipan
 * como Collection base de Src\Shared\Domain.
 */
final class EquipoEtapaGimnasio
{
    public function __construct(
        public readonly Collection $vanguardia,
        public readonly Collection $retaguardia,
    ) {
    }

    /** @return list<int> */
    public function todos(): array
    {
        return [...iterator_to_array($this->vanguardia), ...iterator_to_array($this->retaguardia)];
    }
}
