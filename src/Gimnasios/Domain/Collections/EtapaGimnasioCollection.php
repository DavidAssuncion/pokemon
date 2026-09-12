<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Collections;

use Src\Gimnasios\Domain\DataTransferObjects\EtapaGimnasio;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de etapas del detalle de gimnasio.
 */
final class EtapaGimnasioCollection extends Collection
{
    public string $type = EtapaGimnasio::class;

    /**
     * Frontera — serializa cada etapa al shape del contrato previo.
     *
     * @return list<array{etapa: int, nombre: string}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (EtapaGimnasio $etapa): array => $etapa->toArray());
    }
}
