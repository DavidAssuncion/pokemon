<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de familias candidatas, ordenada por cadena evolutiva.
 */
final class FamiliasCandidatasCollection extends Collection
{
    public string $type = FamiliaCandidata::class;

    /**
     * @param  list<FamiliaCandidata>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * Candidata de la posición indicada o null si no existe.
     */
    public function item(int $indice): ?FamiliaCandidata
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof FamiliaCandidata ? $item : null;
    }
}
