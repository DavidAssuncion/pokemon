<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de datos de pokémon que entran en una batalla.
 */
final class DatosPokemonBatallaCollection extends Collection
{
    public string $type = DatosPokemonBatalla::class;

    /**
     * @param  list<DatosPokemonBatalla>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<DatosPokemonBatalla> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?DatosPokemonBatalla
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof DatosPokemonBatalla ? $item : null;
    }
}
