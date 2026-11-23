<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * La facción ya fue completada y su hábitat ha desaparecido.
 */
final class FaccionCompletada extends ViolacionReglaNegocio
{
    public function __construct(string $nombre)
    {
        parent::__construct("Ya has completado el hábitat de {$nombre}.");
    }
}
