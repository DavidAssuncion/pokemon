<?php

declare(strict_types=1);

namespace App\Providers;

use App\Datagrid\DatagridDefinition;
use App\Datagrid\DatagridRegistry;
use App\Datagrid\RelationFilter;
use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class DatagridServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatagridRegistry::class, fn (): DatagridRegistry => $this->buildRegistry());
    }

    private function buildRegistry(): DatagridRegistry
    {
        $registry = new DatagridRegistry();

        $registry->register('pokemon', new DatagridDefinition(
            model: Pokemon::class,
            searchable: ['name'],
            filterable: [
                'id' => 'id',
                'name' => 'name',
                'visto' => 'pokedex.visto',
                'atrapado' => 'pokedex.atrapado',
            ],
            relationFilters: [
                'types' => new RelationFilter('types', 'type', fn (mixed $value): ?int => $this->tipoId($value)),
                'effort' => new RelationFilter(
                    relation: 'stats',
                    column: 'stat',
                    map: fn (mixed $value): ?int => $this->statId($value),
                    constraint: function (Builder $q, array $mapped): void {
                        $q->getQuery()->whereIn('stat', $mapped);
                        $q->getQuery()->where('effort', '>', 0);
                    },
                ),
            ],
            sortable: [
                'id' => 'id',
                'name' => 'name',
                'visto' => 'pokedex.visto',
                'atrapado' => 'pokedex.atrapado',
            ],
            with: ['types'],
            visible: ['id', 'name', 'visto', 'atrapado'],
            boolFields: ['visto', 'atrapado'],
            itemFields: [
                'icon' => fn (Model $model): string => '/images/iconos_webp/'.($this->requirePokemon($model))->id.'.webp',
                'types' => fn (Model $model): array => $this->typeNames($this->requirePokemon($model)->types),
            ],
            baseQuery: function (Builder $query): Builder {
                $userId = Auth::id();

                $query->getQuery()->leftJoin('pokedex', function (JoinClause $join) use ($userId): void {
                    $join->on('pokedex.pokemon_id', '=', 'pokemon.id');

                    if ($userId !== null) {
                        // Multiplayer: la pokédex del join es solo la del usuario autenticado
                        // (una fila por usuario y pokémon; sin esto el join duplicaría filas).
                        $join->where('pokedex.user_id', $userId);
                    } else {
                        // Sin sesión (CLI/jobs): unión vacía → visto/atrapado NULL ≡ false.
                        $join->whereRaw('1 = 0');
                    }
                });
                $query->getQuery()->select('pokemon.*', 'pokedex.visto', 'pokedex.atrapado');

                return $query;
            },
            counts: fn (): array => $this->pokemonCounts(),
            detail: function (Model $model): array {
                $pokemon = $this->requirePokemon($model);
                $pokemon->loadMissing(['stats', 'types', 'habitats']);

                return [
                    'id' => $pokemon->id,
                    'name' => $pokemon->name,
                    'visto' => (bool) $pokemon->getAttribute('visto'),
                    'atrapado' => (bool) $pokemon->getAttribute('atrapado'),
                    'types' => $this->typeNames($pokemon->types),
                    'stats' => $pokemon->stats
                        ->sortBy(fn (PokemonStat $stat): int => $stat->stat->value)
                        ->map(fn (PokemonStat $stat): array => [
                            'name' => $stat->stat_nombre,
                            'value' => $stat->base_stat,
                        ])
                        ->values()
                        ->toArray(),
                    'habitat_name' => $pokemon->habitats->first()?->name,
                ];
            },
        ));

        $registry->register('pokedex', new DatagridDefinition(
            model: Pokedex::class,
            filterable: ['id' => 'id', 'pokemon_id' => 'pokemon_id', 'visto' => 'visto', 'atrapado' => 'atrapado'],
            sortable: ['id' => 'id', 'pokemon_id' => 'pokemon_id', 'visto' => 'visto', 'atrapado' => 'atrapado'],
            with: ['pokemon'],
            visible: ['id', 'pokemon_id', 'visto', 'atrapado'],
            boolFields: ['visto', 'atrapado'],
        ));

        $registry->register('reclutado', new DatagridDefinition(
            model: Reclutado::class,
            searchable: ['nombre'],
            filterable: ['id' => 'id', 'pokemon_id' => 'pokemon_id', 'es_shiny' => 'es_shiny'],
            sortable: ['id' => 'id', 'pokemon_id' => 'pokemon_id', 'nombre' => 'nombre', 'es_shiny' => 'es_shiny'],
            with: ['pokemon', 'teamMember'],
            visible: ['id', 'pokemon_id', 'nombre', 'es_shiny'],
            boolFields: ['es_shiny'],
        ));

        $registry->register('team', new DatagridDefinition(
            model: Team::class,
            searchable: ['name'],
            filterable: ['id' => 'id', 'name' => 'name'],
            sortable: ['id' => 'id', 'name' => 'name'],
            with: ['members'],
            visible: ['id', 'name', 'created_at', 'updated_at'],
        ));

        $registry->register('habitat', new DatagridDefinition(
            model: Habitat::class,
            searchable: ['name'],
            filterable: ['id' => 'id', 'province_id' => 'province_id', 'name' => 'name'],
            sortable: ['id' => 'id', 'province_id' => 'province_id', 'name' => 'name'],
            with: ['province', 'pokemon'],
            visible: ['id', 'province_id', 'name'],
        ));

        $registry->register('province', new DatagridDefinition(
            model: Province::class,
            searchable: ['name'],
            filterable: ['id' => 'id', 'name' => 'name'],
            sortable: ['id' => 'id', 'name' => 'name'],
            with: ['habitats'],
            visible: ['id', 'name'],
        ));

        return $registry;
    }

    private function requirePokemon(Model $model): Pokemon
    {
        if (! $model instanceof Pokemon) {
            throw new LogicException(sprintf('Datagrid pokemon resolvers require a Pokemon model, got %s.', $model::class));
        }

        return $model;
    }

    /**
     * @param  Collection<int, PokemonType>  $types
     * @return list<string>
     */
    private function typeNames(Collection $types): array
    {
        return array_values($types->map(fn (PokemonType $type): string => $type->tipo_nombre)->all());
    }

    /**
     * @return array{total: int, vistos: int, atrapados: int, no_vistos: int}
     */
    private function pokemonCounts(): array
    {
        $total = Pokemon::query()->getQuery()->count();
        $userId = Auth::id();

        if ($userId === null) {
            // Sin sesión (CLI/jobs): misma semántica que la unión vacía del baseQuery.
            return [
                'total' => $total,
                'vistos' => 0,
                'atrapados' => 0,
                'no_vistos' => $total,
            ];
        }

        // El global scope de Pokedex ya acota al usuario autenticado; el where explícito
        // mantiene el contrato aunque se consultara desde CLI sin scope activo.
        $pokedex = Pokedex::query()->getQuery()->where('user_id', $userId);
        $vistos = (clone $pokedex)->where('visto', true)->count();
        $atrapados = (clone $pokedex)->where('atrapado', true)->count();

        return [
            'total' => $total,
            'vistos' => $vistos,
            'atrapados' => $atrapados,
            'no_vistos' => max(0, $total - $vistos),
        ];
    }

    private function statId(mixed $value): ?int
    {
        $id = $this->labelToId($value, StatEnum::cases());

        return $id === 0 ? null : $id;
    }

    private function tipoId(mixed $value): ?int
    {
        return $this->labelToId($value, TipoEnum::cases());
    }

    /**
     * @param  list<StatEnum|TipoEnum>  $cases
     */
    private function labelToId(mixed $value, array $cases): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $label = strtolower(trim((string) $value));

        foreach ($cases as $case) {
            if (strtolower($case->label()) === $label) {
                return $case->value;
            }
        }

        return null;
    }
}
