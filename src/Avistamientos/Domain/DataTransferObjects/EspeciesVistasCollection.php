<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada con el estado «visto» de las especies consultadas.
 */
final class EspeciesVistasCollection extends Collection
{
    public string $type = EspecieVista::class;

    /**
     * @param  list<EspecieVista>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
