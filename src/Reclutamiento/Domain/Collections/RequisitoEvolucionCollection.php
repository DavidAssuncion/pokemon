<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\Collections;

use Src\Reclutamiento\Domain\DataTransferObjects\RequisitoEvolucion;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de requisitos de evolución (un requisito por tipo).
 */
final class RequisitoEvolucionCollection extends Collection
{
    public string $type = RequisitoEvolucion::class;

    /**
     * True si cumple TODOS los requisitos y existe al menos uno
     * (equivale al antiguo `cumpleRequisitos(array)` de ServicioEvolucion).
     */
    public function cumple(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return $this->every(fn (RequisitoEvolucion $requisito): bool => $requisito->cumple());
    }

    /**
     * Frontera — serializa cada requisito al shape del contrato previo.
     *
     * @return list<array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (RequisitoEvolucion $requisito): array => $requisito->toArray());
    }
}
