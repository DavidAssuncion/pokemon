<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Collections;

use Src\HabitatsTemporales\Domain\DataTransferObjects\FaccionResumen;
use Src\Shared\Domain\Collection;

/**
 * Colección de facciones visibles en la provincia Temporal.
 */
final class FaccionResumenCollection extends Collection
{
    public string $type = FaccionResumen::class;

    /**
     * @return list<array{slug: string, nombre: string, descripcion: string, imagen_url: string, color: string, oleada_actual: int, combate_actual: int, total_oleadas: int, combates_por_oleada: int, estado: string, motivo_bloqueo: string|null, enlace: string}>
     */
    public function toArray(): array
    {
        return $this->map(static fn (FaccionResumen $faccion): array => $faccion->toArray());
    }
}
