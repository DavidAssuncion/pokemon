<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\Collections;

use Src\Reclutamiento\Domain\DataTransferObjects\OpcionEvolucion;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de opciones de evolución de un pokémon.
 */
final class OpcionEvolucionCollection extends Collection
{
    public string $type = OpcionEvolucion::class;

    /**
     * Frontera — serializa cada opción al shape del contrato previo.
     *
     * @return list<array{pokemon_id: int, nombre: string, imagen: string, requisitos: list<array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}>, puede_evolucionar: bool}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (OpcionEvolucion $opcion): array => $opcion->toArray());
    }
}
