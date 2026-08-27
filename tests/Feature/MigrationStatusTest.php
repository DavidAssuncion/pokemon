<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_pokedex_table_exists_and_has_columns(): void
    {
        $this->assertTrue(Schema::hasTable('pokedex'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'id'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'pokemon_id'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'visto'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'atrapado'));
    }

    public function test_reclutables_table_exists_and_has_columns(): void
    {
        $this->assertTrue(Schema::hasTable('reclutables'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'id'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'pokemon_id'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'cantidad'));
    }

    public function test_caramelos_table_exists_and_has_columns(): void
    {
        $this->assertTrue(Schema::hasTable('caramelos'));
        $this->assertTrue(Schema::hasColumn('caramelos', 'id'));
        $this->assertTrue(Schema::hasColumn('caramelos', 'evolution_chain_id'));
        $this->assertTrue(Schema::hasColumn('caramelos', 'cantidad'));
    }

    public function test_exploraciones_activas_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasTable('exploraciones_activas'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'habitat_id'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'nivel'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'duracion_horas'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'hora_limite'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'indefinido'));
    }

    public function test_habitats_has_pokemons_column(): void
    {
        $this->assertTrue(Schema::hasTable('habitats'));
        $this->assertTrue(Schema::hasColumn('habitats', 'pokemons'));
    }

    public function test_teams_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('teams'));
        $this->assertTrue(Schema::hasColumn('teams', 'id'));
        $this->assertTrue(Schema::hasColumn('teams', 'name'));
    }

    public function test_team_members_table_exists_and_has_columns(): void
    {
        $this->assertTrue(Schema::hasTable('team_members'));
        $this->assertTrue(Schema::hasColumn('team_members', 'id'));
        $this->assertTrue(Schema::hasColumn('team_members', 'team_id'));
        $this->assertTrue(Schema::hasColumn('team_members', 'pokemon_id'));
        $this->assertTrue(Schema::hasColumn('team_members', 'slot'));
        $this->assertTrue(Schema::hasColumn('team_members', 'behavior'));
    }

    public function test_pokedex_table_has_unique_constraint_on_pokemon_id(): void
    {
        $indexes = Schema::getIndexes('pokedex');
        $uniqueOnPokemon = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true && in_array('pokemon_id', $index['columns'], true);
        });

        $this->assertTrue($uniqueOnPokemon, 'pokedex table should have a unique index on pokemon_id');
    }

    public function test_caramelos_table_has_unique_constraint_on_evolution_chain_id(): void
    {
        $indexes = Schema::getIndexes('caramelos');
        $uniqueOnChain = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true && in_array('evolution_chain_id', $index['columns'], true);
        });

        $this->assertTrue($uniqueOnChain, 'caramelos table should have a unique index on evolution_chain_id');
    }

    public function test_users_has_experiencia_column(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumn('users', 'experiencia'));
    }

    public function test_caramelos_ev_table_exists_and_has_unique_stat(): void
    {
        $this->assertTrue(Schema::hasTable('caramelos_ev'));
        $this->assertTrue(Schema::hasColumn('caramelos_ev', 'stat'));
        $this->assertTrue(Schema::hasColumn('caramelos_ev', 'cantidad'));

        $indexes = Schema::getIndexes('caramelos_ev');
        $uniqueOnStat = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true && in_array('stat', $index['columns'], true);
        });

        $this->assertTrue($uniqueOnStat, 'caramelos_ev table should have a unique index on stat');
    }
}
