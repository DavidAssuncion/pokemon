<?php

declare(strict_types=1);

namespace App\Datagrid;

use Closure;

/**
 * Filtro de relación (whereHas) para el datagrid.
 *
 * El cliente solo puede referirse a la clave pública registrada (p. ej.
 * `types`); la columna real y el mapeo de valores se definen aquí.
 */
final class RelationFilter
{
    /**
     * @param Closure(mixed): mixed|null $map Transforma el valor del cliente antes del whereIn
     */
    public function __construct(
        public readonly string $relation,
        public readonly string $column,
        public readonly ?Closure $map = null,
    ) {
    }
}
