<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\Collections;

use Src\Equipos\Domain\DataTransferObjects\EquipoInicialResumen;
use Src\Shared\Domain\Collection;

final class EquipoInicialResumenCollection extends Collection
{
    public string $type = EquipoInicialResumen::class;

    /**
     * @param  list<EquipoInicialResumen>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<EquipoInicialResumen> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?EquipoInicialResumen
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof EquipoInicialResumen ? $item : null;
    }
}
