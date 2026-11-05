<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\DataTransferObjects\OpcionObjeto;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de opciones de objeto para la UI de combate.
 */
final class OpcionObjetoCollection extends Collection
{
    public string $type = OpcionObjeto::class;

    /**
     * @param  list<OpcionObjeto>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<OpcionObjeto> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Frontera JSON: lista de opciones serializadas.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (OpcionObjeto $opcion): array => $opcion->toArray(),
            $this->items,
        );
    }
}
