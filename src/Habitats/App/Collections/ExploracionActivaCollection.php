<?php

declare(strict_types=1);

namespace Src\Habitats\App\Collections;

use App\Models\ExploracionActiva;
use Src\Shared\Domain\Collection;

/**
 * Exploraciones activas de un hábitat (modelos Eloquent con reclutado y
 * pokémon cargados). Vive en App porque Eloquent no pertenece al dominio.
 */
final class ExploracionActivaCollection extends Collection
{
    public string $type = ExploracionActiva::class;

    /**
     * @param  list<ExploracionActiva>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<ExploracionActiva> */
    public function all(): array
    {
        return $this->items;
    }

    public function en(int $indice): ?ExploracionActiva
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof ExploracionActiva ? $item : null;
    }
}
