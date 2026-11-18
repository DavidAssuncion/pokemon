<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\Collections;

use Src\Forja\Domain\Piezas\DataTransferObjects\RecetaPieza;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de recetas de pieza.
 */
final class RecetasPiezaCollection extends Collection
{
    public string $type = RecetaPieza::class;

    public function porClave(string $clave): ?RecetaPieza
    {
        foreach ($this as $receta) {
            if ($receta instanceof RecetaPieza && $receta->clave === $clave) {
                return $receta;
            }
        }

        return null;
    }
}
