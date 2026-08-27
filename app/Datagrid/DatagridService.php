<?php

declare(strict_types=1);

namespace App\Datagrid;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Servicio de consulta JSON reutilizable con whitelist por modelo.
 *
 * Aplica search (LIKE), filtros exactos/IN/whereHas, ordenación y
 * paginación, siempre restringido a los campos registrados en
 * DatagridDefinition. Los parámetros no whitelisted se ignoran
 * silenciosamente.
 */
final class DatagridService
{
    public function __construct(
        private readonly DatagridRegistry $registry
    ) {
    }

    public function registered(string $slug): bool
    {
        return $this->registry->has($slug);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{
     *         total: int,
     *         page: int,
     *         per_page: int,
     *         last_page: int,
     *         counts: array<string, int>|null
     *     }
     * }
     */
    public function list(string $slug, array $params): array
    {
        $definition = $this->registry->get($slug);
        $query = $this->baseQuery($definition);

        $this->applySearch($query, $definition, $params['search'] ?? null);
        $this->applyFilters($query, $definition, $params['filter'] ?? []);
        $this->applySort($query, $definition, $params['sort'] ?? null, $params['order'] ?? 'asc');

        $perPage = max(1, min(200, (int) ($params['per_page'] ?? 100)));
        $page = max(1, (int) ($params['page'] ?? 1));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = array_map(
            fn (Model $model): array => $this->toVisibleArray($model, $definition),
            $paginator->items()
        );

        return [
            'data' => array_values($data),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'counts' => $definition->counts !== null ? ($definition->counts)() : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(string $slug, int $id): ?array
    {
        $definition = $this->registry->get($slug);
        $query = $this->baseQuery($definition);

        $model = $query->find($id);

        if ($model === null) {
            return null;
        }

        if ($definition->detail !== null) {
            return ($definition->detail)($model);
        }

        return $this->toVisibleArray($model, $definition);
    }

    /**
     * @return Builder<Model>
     */
    private function baseQuery(DatagridDefinition $definition): Builder
    {
        $query = $definition->model::query();

        if ($definition->baseQuery !== null) {
            $query = ($definition->baseQuery)($query);
        }

        return $query->with($definition->with);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySearch(Builder $query, DatagridDefinition $definition, mixed $search): void
    {
        if (! is_string($search) || trim($search) === '' || $definition->searchable === []) {
            return;
        }

        $term = '%'.trim($search).'%';
        $table = $query->getModel()->getTable();

        $query->getQuery()->where(function (QueryBuilder $q) use ($definition, $term, $table): void {
            foreach ($definition->searchable as $column) {
                $q->orWhere($this->qualifyColumn($table, $column), 'like', $term);
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyFilters(Builder $query, DatagridDefinition $definition, mixed $filters): void
    {
        if (! is_array($filters)) {
            return;
        }

        $table = $query->getModel()->getTable();

        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $field = (string) $field;

            if (isset($definition->relationFilters[$field])) {
                $this->applyRelationFilter($query, $definition->relationFilters[$field], $value);

                continue;
            }

            if (! array_key_exists($field, $definition->filterable)) {
                continue;
            }

            $column = $this->qualifyColumn($table, $definition->filterable[$field]);
            $values = $this->splitValues($value);

            if (in_array($field, $definition->boolFields, true)) {
                $values = array_map(fn (string $v): bool => $this->toBool($v), $values);

                if (in_array(false, $values, true)) {
                    // Columnas booleanas sobre leftJoin: la fila ausente es NULL,
                    // y NULL también significa false (no avistado / no atrapado).
                    $query->getQuery()->where(function (QueryBuilder $q) use ($column, $values): void {
                        $q->whereIn($column, $values);
                        $q->orWhereNull($column);
                    });

                    continue;
                }
            }

            $query->getQuery()->whereIn($column, $values);
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyRelationFilter(Builder $query, RelationFilter $filter, mixed $value): void
    {
        $mapped = [];

        foreach ($this->splitValues($value) as $v) {
            $resolved = $filter->map !== null ? ($filter->map)($v) : $v;

            if ($resolved !== null) {
                $mapped[] = $resolved;
            }
        }

        if ($mapped === []) {
            return;
        }

        $query->whereHas($filter->relation, function (Builder $q) use ($filter, $mapped): void {
            if ($filter->constraint !== null) {
                ($filter->constraint)($q, $mapped);

                return;
            }

            $q->getQuery()->whereIn($filter->column, $mapped);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySort(Builder $query, DatagridDefinition $definition, mixed $sort, mixed $order): void
    {
        if (! is_string($sort) || $sort === '' || ! array_key_exists($sort, $definition->sortable)) {
            return;
        }

        $direction = is_string($order) && strtolower($order) === 'desc' ? 'desc' : 'asc';

        $query->getQuery()->orderBy(
            $this->qualifyColumn($query->getModel()->getTable(), $definition->sortable[$sort]),
            $direction
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toVisibleArray(Model $model, DatagridDefinition $definition): array
    {
        $row = $model->only($definition->visible);

        foreach ($definition->boolFields as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = $this->toBool($row[$field]);
            }
        }

        foreach ($definition->itemFields as $field => $resolver) {
            $row[$field] = $resolver($model);
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    private function splitValues(mixed $value): array
    {
        $values = str_contains((string) $value, ',')
            ? explode(',', (string) $value)
            : [(string) $value];

        return array_values(array_filter(array_map('trim', $values), fn (string $v): bool => $v !== ''));
    }

    private function qualifyColumn(string $table, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $table.'.'.$column;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 't', 'on', 'yes'], true);
        }

        return (bool) $value;
    }
}
