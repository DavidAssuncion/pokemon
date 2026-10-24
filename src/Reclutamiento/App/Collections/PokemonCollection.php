<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App\Collections;

use App\Models\Pokemon;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de modelos Pokemon usada por los casos de uso de
 * reclutamiento (opciones de evolución). Vive en App porque los modelos
 * Eloquent no pertenecen al dominio.
 */
final class PokemonCollection extends Collection
{
    public string $type = Pokemon::class;

    /**
     * @param  list<Pokemon>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<Pokemon> */
    public function all(): array
    {
        return $this->items;
    }

    public function primero(): ?Pokemon
    {
        $primero = $this->first();

        return $primero instanceof Pokemon ? $primero : null;
    }

    public function porId(int $id): ?Pokemon
    {
        foreach ($this->items as $pokemon) {
            if ($pokemon->id === $id) {
                return $pokemon;
            }
        }

        return null;
    }
}
