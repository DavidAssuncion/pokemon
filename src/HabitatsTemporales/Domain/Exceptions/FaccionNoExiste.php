<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\RecursoNoExiste;

/**
 * La facción no existe en el catálogo o todavía no está desbloqueada.
 */
final class FaccionNoExiste extends RecursoNoExiste
{
    public function __construct(string $slug)
    {
        parent::__construct("La facción «{$slug}» no existe o no está disponible.");
    }
}
