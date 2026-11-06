<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Perdidas;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de objetos perdidos por derrota.
 */
final class ColeccionObjetosPerdidos extends Collection
{
    public string $type = ObjetoPerdido::class;

    /**
     * @param  list<ObjetoPerdido>  $objetos
     */
    public function __construct(array $objetos = [])
    {
        parent::__construct($objetos);
    }

    /**
     * @return list<ObjetoPerdido>
     */
    public function all(): array
    {
        return $this->items;
    }
}
