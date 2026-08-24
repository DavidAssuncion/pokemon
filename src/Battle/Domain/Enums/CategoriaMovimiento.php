<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Categoría de un movimiento: físico, especial o de estado.
 */
enum CategoriaMovimiento: string
{
    case FISICO = 'fisico';
    case ESPECIAL = 'especial';
    case ESTADO = 'estado';
}
