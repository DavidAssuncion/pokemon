<?php

declare(strict_types=1);

namespace App\Datagrid;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtro de relación (whereHas) para el datagrid.
 *
 * El cliente solo puede referirse a la clave pública registrada (p. ej.
 * `types`); la columna real y el mapeo de valores se definen aquí.
 */
final class RelationFilter
{
    /**
     * @param  Closure(mixed): mixed|null  $map  Transforma el valor del cliente antes del whereIn
     * @param  Closure(Builder<Model>, list<mixed>): void|null  $constraint  Constraint custom del whereHas (por defecto: whereIn sobre $column)
     */
    public function __construct(
        public readonly string $relation,
        public readonly string $column,
        public readonly ?Closure $map = null,
        public readonly ?Closure $constraint = null,
    ) {
    }
}
