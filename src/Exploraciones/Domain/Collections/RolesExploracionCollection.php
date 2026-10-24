<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Collections;

use Src\Exploraciones\Domain\RolExploracion;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de roles de un equipo de exploración.
 */
final class RolesExploracionCollection extends Collection
{
    public string $type = RolExploracion::class;

    /**
     * @param  list<RolExploracion>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<RolExploracion> */
    public function all(): array
    {
        return $this->items;
    }

    public function valores(): StringCollection
    {
        return new StringCollection(
            $this->map(fn (RolExploracion $rol): string => $rol->value)
        );
    }
}
