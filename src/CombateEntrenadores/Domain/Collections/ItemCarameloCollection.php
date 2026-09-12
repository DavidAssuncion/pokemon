<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\Collections;

use Src\CombateEntrenadores\Domain\DataTransferObjects\ItemCaramelo;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de caramelos del modal de victoria.
 */
final class ItemCarameloCollection extends Collection
{
    public string $type = ItemCaramelo::class;

    /**
     * Frontera — serializa cada caramelo al shape del contrato previo.
     *
     * @return list<array{nombre: string, imagen: string, cantidad: int}>
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return $this->map(fn (ItemCaramelo $caramelo): array => $caramelo->toArray());
    }
}
