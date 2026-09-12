<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\AccionBatalla;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de acciones candidatas (AccionBatalla) para la IA de combate.
 *
 * Sustituye los arrays AccionBatalla[] que generaban RespuestaRival y
 * SelectorAccionIA (deuda F5b). El orden (sort estable) y el filtrado se
 * delegan en la base Collection; primeras() recorta el top-N.
 */
final class AccionesPosiblesCollection extends Collection
{
    public string $type = AccionBatalla::class;

    /**
     * Nueva colección con las primeras N acciones en orden (top-N).
     */
    public function primeras(int $n): static
    {
        return new static(array_slice($this->items, 0, $n));
    }
}
