<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

/**
 * La clave consultada no existe en el catálogo compartido de items.
 */
final class ItemDesconocido extends ViolacionReglaNegocio
{
    public static function paraClave(string $clave): self
    {
        return new self("Item desconocido: {$clave}");
    }
}
