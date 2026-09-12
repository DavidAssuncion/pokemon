<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\Collections;

use Src\Mazmorras\Domain\DataTransferObjects\PisoMazmorra;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de pisos de la mazmorra de un hábitat.
 */
final class PisoMazmorraCollection extends Collection
{
    public string $type = PisoMazmorra::class;

    /**
     * Frontera — serializa cada piso al shape del contrato previo.
     *
     * @return list<array{piso: int, nombre: string, estado: string, cooldown_hasta: string|null}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (PisoMazmorra $piso): array => $piso->toArray());
    }
}
