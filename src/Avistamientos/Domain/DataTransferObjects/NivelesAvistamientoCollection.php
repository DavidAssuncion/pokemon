<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de niveles de avistamiento, ordenada de menor a mayor.
 */
final class NivelesAvistamientoCollection extends Collection
{
    public string $type = NivelAvistamiento::class;

    /**
     * @param  list<NivelAvistamiento>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
