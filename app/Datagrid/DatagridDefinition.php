<?php

declare(strict_types=1);

namespace App\Datagrid;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist de consulta para un modelo del datagrid.
 *
 * Solo los campos declarados aquí pueden usarse en búsqueda, filtros,
 * ordenación y visibilidad. Nada de lo que envíe el cliente se aplica
 * al query sin pasar por esta definición (anti inyección).
 */
final class DatagridDefinition
{
    /**
     * @param class-string<Model> $model
     * @param list<string> $searchable Columnas SQL (cualificadas o no) para el LIKE de `search`
     * @param array<string, string> $filterable Clave pública del filtro => columna SQL
     * @param array<string, RelationFilter> $relationFilters Filtros vía whereHas (p. ej. `types`)
     * @param array<string, string> $sortable Clave pública del sort => columna SQL
     * @param list<string> $with Relaciones a eager load en el listado
     * @param list<string> $visible Campos incluidos en cada item de `data`
     * @param list<string> $boolFields Campos visibles/filtrables normalizados a booleano
     * @param array<string, Closure(Model): mixed> $itemFields Campos calculados por item (p. ej. `icon`, `types`)
     * @param Closure(Builder<Model>): Builder<Model>|null $baseQuery Personalización del query (p. ej. join de la Pokédex)
     * @param Closure(): array<string, int>|null $counts Contadores globales para `meta.counts`
     * @param Closure(Model): array<string, mixed>|null $detail Resolvedor del detalle (por defecto: campos visibles)
     */
    public function __construct(
        public readonly string $model,
        public readonly array $searchable = [],
        public readonly array $filterable = [],
        public readonly array $relationFilters = [],
        public readonly array $sortable = [],
        public readonly array $with = [],
        public readonly array $visible = [],
        public readonly array $boolFields = [],
        public readonly array $itemFields = [],
        public readonly ?Closure $baseQuery = null,
        public readonly ?Closure $counts = null,
        public readonly ?Closure $detail = null,
    ) {
    }
}
