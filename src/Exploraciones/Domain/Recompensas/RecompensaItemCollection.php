<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de items obtenidos como botín.
 */
final class RecompensaItemCollection extends Collection
{
    public string $type = RecompensaItem::class;

    /**
     * @param  list<RecompensaItem>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * @return list<RecompensaItem>
     */
    public function all(): array
    {
        return $this->items;
    }
}
