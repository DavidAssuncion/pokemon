<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\Collections;

use Src\Habitats\Domain\DataTransferObjects\PokemonBasico;
use Src\Shared\Domain\Collection;

final class PokemonBasicoCollection extends Collection
{
    public string $type = PokemonBasico::class;

    /** @return list<array{id: int, name: string}> */
    public function toArray(): array
    {
        return $this->map(fn (PokemonBasico $pokemon): array => $pokemon->toArray());
    }
}
