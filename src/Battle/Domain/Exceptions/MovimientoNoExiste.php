<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\RecursoNoExiste;

final class MovimientoNoExiste extends RecursoNoExiste
{
    public static function porId(int $id): self
    {
        return new self("El movimiento {$id} no existe.");
    }

    public static function porSlug(string $slug): self
    {
        return new self("El movimiento '{$slug}' no existe.");
    }
}
