<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Collections;

use Src\Habitats\Domain\DataTransferObjects\PokemonCadenaBase;
use Src\Shared\Domain\Collection;

final class PokemonCadenaCollection extends Collection
{
    public string $type = PokemonCadenaBase::class;

    /**
     * @param  list<PokemonCadenaBase>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<PokemonCadenaBase> */
    public function all(): array
    {
        return $this->items;
    }

    public function primero(): ?PokemonCadenaBase
    {
        $primero = $this->first();

        return $primero instanceof PokemonCadenaBase ? $primero : null;
    }
}
