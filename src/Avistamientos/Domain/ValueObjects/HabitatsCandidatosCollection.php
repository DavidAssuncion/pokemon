<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\ValueObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de hábitats candidatos, ordenada por id ascendente.
 */
final class HabitatsCandidatosCollection extends Collection
{
    public string $type = HabitatCandidato::class;

    /**
     * @param  list<HabitatCandidato>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * Candidato de la posición indicada o null si no existe.
     */
    public function item(int $indice): ?HabitatCandidato
    {
        $item = $this->items[$indice] ?? null;

        return $item instanceof HabitatCandidato ? $item : null;
    }
}
