<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Collections;

use Src\Habitats\Domain\DataTransferObjects\MiembroCadena;
use Src\Shared\Domain\Collection;

final class MiembrosCadenaCollection extends Collection
{
    public string $type = MiembroCadena::class;

    /**
     * @param  list<MiembroCadena>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<MiembroCadena> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?MiembroCadena
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof MiembroCadena ? $item : null;
    }

    public function primero(): ?MiembroCadena
    {
        $primero = $this->first();

        return $primero instanceof MiembroCadena ? $primero : null;
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->map(fn (MiembroCadena $miembro): int => $miembro->id);
    }
}
