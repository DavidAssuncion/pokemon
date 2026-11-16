<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de especies presentes en el pool de un hábitat.
 */
final class EspeciesEnHabitatCollection extends Collection
{
    public string $type = EspecieEnHabitat::class;

    /**
     * @param  list<EspecieEnHabitat>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
