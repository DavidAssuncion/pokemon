<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Collections;

use Src\Gimnasios\Domain\DataTransferObjects\HabitatRecomendado;
use Src\Shared\Domain\Collection;

/**
 * Colección ordenada de hábitats recomendados para un gimnasio.
 */
final class HabitatRecomendadoCollection extends Collection
{
    public string $type = HabitatRecomendado::class;

    /**
     * @return list<array{habitat_id: int, nombre: string, provincia: string, coincidencias: int, total: int, nivel_minimo: int}>
     */
    public function toArray(): array
    {
        return $this->map(fn (HabitatRecomendado $habitat): array => $habitat->toArray());
    }
}
