<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\Collections;

use Src\Equipos\Domain\DataTransferObjects\EquipoDefinido;
use Src\Shared\Domain\Collection;

final class EquipoDefinidoCollection extends Collection
{
    public string $type = EquipoDefinido::class;

    public function porClave(string $key): ?EquipoDefinido
    {
        foreach ($this->items as $equipo) {
            if ($equipo->key === $key) {
                return $equipo;
            }
        }

        return null;
    }
}
