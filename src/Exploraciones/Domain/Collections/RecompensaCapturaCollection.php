<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Collections;

use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Shared\Domain\Collection;

final class RecompensaCapturaCollection extends Collection
{
    public string $type = RecompensaCaptura::class;

    /**
     * @param  list<RecompensaCaptura>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<RecompensaCaptura> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?RecompensaCaptura
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof RecompensaCaptura ? $item : null;
    }

    /**
     * @return list<array{pokemon_id: int, cantidad: int, es_shiny: bool}>
     */
    public function toArray(): array
    {
        return $this->map(fn (RecompensaCaptura $captura): array => [
            'pokemon_id' => $captura->pokemonId,
            'cantidad' => $captura->cantidad,
            'es_shiny' => $captura->esShiny,
        ]);
    }
}
