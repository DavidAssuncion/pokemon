<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Collections;

use Src\Shared\Domain\Collection;
use Src\Shared\Domain\DataTransferObjects\ItemCaramelo;

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
