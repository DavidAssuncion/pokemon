<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\RecursoNoExiste;

/**
 * La receta solicitada no existe en el catálogo estático de forja.
 */
final class RecetaNoExiste extends RecursoNoExiste
{
    public static function paraClave(string $clave): self
    {
        return new self("Receta desconocida: {$clave}");
    }
}
