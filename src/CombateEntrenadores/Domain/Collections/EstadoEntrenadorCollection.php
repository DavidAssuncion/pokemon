<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\Collections;

use Src\CombateEntrenadores\Domain\DataTransferObjects\EstadoEntrenador;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de estados de entrenador de un nivel de hábitat.
 */
final class EstadoEntrenadorCollection extends Collection
{
    public string $type = EstadoEntrenador::class;

    /**
     * Frontera — serializa cada estado al shape del contrato previo.
     *
     * @return list<array{indice: int, desbloqueado: bool}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (EstadoEntrenador $entrenador): array => $entrenador->toArray());
    }
}
