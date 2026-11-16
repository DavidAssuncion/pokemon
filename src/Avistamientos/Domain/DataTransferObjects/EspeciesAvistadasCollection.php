<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de especies afectadas por el avistamiento.
 */
final class EspeciesAvistadasCollection extends Collection
{
    public string $type = EspecieAvistada::class;

    /**
     * @param  list<EspecieAvistada>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }
}
