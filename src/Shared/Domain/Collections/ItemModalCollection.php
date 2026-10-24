<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Collections;

use Src\Shared\Domain\Collection;
use Src\Shared\Domain\DataTransferObjects\ItemModal;

final class ItemModalCollection extends Collection
{
    public string $type = ItemModal::class;

    /**
     * @return list<array{src: string, alt: string, cantidad: int, nombre: string|null}>
     */
    public function toArray(): array
    {
        return $this->map(fn (ItemModal $item): array => $item->toArray());
    }
}
