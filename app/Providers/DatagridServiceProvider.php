<?php

declare(strict_types=1);

namespace App\Providers;

use App\Datagrid\DatagridDefinition;
use App\Datagrid\DatagridRegistry;
use App\Datagrid\RelationFilter;
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
use Illuminate\Support\ServiceProvider;

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
            ],
            sortable: [
                'id' => 'id',
                'name' => 'name',
                'visto' => 'pokedex.visto',
                'atrapado' => 'pokedex.atrapado',
            ],
            visible: ['id', 'name', 'visto', 'atrapado'],
            boolFields: ['visto', 'atrapado'],
            baseQuery: function (Builder $query): Builder {
                $query->getQuery()->leftJoin('pokedex', 'pokedex.pokemon_id', '=', 'pokemon.id');
                $query->getQuery()->select('pokemon.*', 'pokedex.visto', 'pokedex.atrapado');

                return $query;
            },
            counts: fn (): array => [
                'total' => Pokemon::query()->getQuery()->count(),
                'vistos' => Pokedex::query()->getQuery()->where('visto', true)->count(),
                'atrapados' => Pokedex::query()->getQuery()->where('atrapado', true)->count(),
                'no_vistos' => max(0, Pokemon::query()->getQuery()->count() - Pokedex::query()->getQuery()->where('visto', true)->count()),
            ],
            detail: function (Model $model): array {
                if (! $model instanceof Pokemon) {
                    throw new \LogicException('Datagrid pokemon detail resolver requires a Pokemon model.');
                }

                $model->loadMissing(['stats', 'types', 'habitats']);

                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'visto' => (bool) $model->getAttribute('visto'),
                    'atrapado' => (bool) $model->getAttribute('atrapado'),
                    'types' => $model->types
                        ->map(fn (PokemonType $type): string => $type->tipo_nombre)
                        ->values()
                        ->toArray(),
                    'stats' => $model->stats
                        ->sortBy(fn (PokemonStat $stat): int => $stat->stat->value)
                        ->map(fn (PokemonStat $stat): array => [
                            'name' => $stat->stat_nombre,
                            'value' => $stat->base_stat,
                        ])
                        ->values()
                        ->toArray(),
                    'habitat_name' => $model->habitats->first()?->name,
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

    private function tipoId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $label = strtolower(trim((string) $value));

        foreach (TipoEnum::cases() as $case) {
            if (strtolower($case->label()) === $label) {
                return $case->value;
            }
        }

        return null;
    }
}
