<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Collections;

use Src\Gimnasios\Domain\DataTransferObjects\GimnasioResumen;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de resúmenes de gimnasio (listado público).
 */
final class GimnasioResumenCollection extends Collection
{
    public string $type = GimnasioResumen::class;

    /**
     * Frontera — serializa cada resumen al shape del contrato previo.
     *
     * @return list<array{slug: string, medalla: string, tipo: int, nivel_minimo: int, nivel_jugador: int, etapa_actual: int, estado: string}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (GimnasioResumen $gimnasio): array => $gimnasio->toArray());
    }
}
