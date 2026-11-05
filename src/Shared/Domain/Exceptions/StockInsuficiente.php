<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

/**
 * El jugador no tiene existencias suficientes de un item para completar
 * la operación. Se mapea a HTTP 422.
 */
final class StockInsuficiente extends ViolacionReglaNegocio
{
    public static function para(
        string $clave,
        int $requerido,
        int $disponible,
        ?string $mensaje = null,
    ): self {
        return new self(
            $mensaje ?? "No tienes suficientes unidades de {$clave}: necesitas {$requerido} y tienes {$disponible}",
        );
    }
}
