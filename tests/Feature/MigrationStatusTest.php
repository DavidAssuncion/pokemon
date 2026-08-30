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
        $this->assertTrue(Schema::hasColumn('pokedex', 'user_id'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'pokemon_id'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'visto'));
        $this->assertTrue(Schema::hasColumn('pokedex', 'atrapado'));
    }

    public function test_reclutables_table_exists_and_has_columns(): void
    {
        $this->assertTrue(Schema::hasTable('reclutables'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'id'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'user_id'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'pokemon_id'));
        $this->assertTrue(Schema::hasColumn('reclutables', 'cantidad'));
    }

    public function test_caramelos_tables_y_reclutados_exp_tipo_ya_no_existen(): void
    {
        // Fase 1 multiplayer: los caramelos se volcaron a player_inventory y la exp de tipo
        // a reclutados.exp.tipos (migraciones 2026_08_29_000008/000009).
        $this->assertFalse(Schema::hasTable('caramelos'));
        $this->assertFalse(Schema::hasTable('caramelos_ev'));
        $this->assertFalse(Schema::hasTable('caramelos_tipo'));
        $this->assertFalse(Schema::hasTable('reclutados_exp_tipo'));
    }

    public function test_player_inventory_table_exists_and_has_columns(): void
    {
        $this->assertTrue(Schema::hasTable('player_inventory'));
        $this->assertTrue(Schema::hasColumn('player_inventory', 'id'));
        $this->assertTrue(Schema::hasColumn('player_inventory', 'user_id'));
        $this->assertTrue(Schema::hasColumn('player_inventory', 'item_key'));
        $this->assertTrue(Schema::hasColumn('player_inventory', 'cantidad'));
        $this->assertTrue(Schema::hasColumn('player_inventory', 'created_at'));
        $this->assertTrue(Schema::hasColumn('player_inventory', 'updated_at'));
    }

    public function test_exploraciones_activas_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasTable('exploraciones_activas'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'user_id'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'habitat_id'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'nivel'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'duracion_horas'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'hora_limite'));
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'indefinido'));
    }

    public function test_habitats_has_pokemons_and_min_lvl_columns(): void
    {
        $this->assertTrue(Schema::hasTable('habitats'));
        $this->assertTrue(Schema::hasColumn('habitats', 'pokemons'));
        $this->assertTrue(Schema::hasColumn('habitats', 'min_lvl_1'));
        $this->assertTrue(Schema::hasColumn('habitats', 'min_lvl_2'));
        $this->assertTrue(Schema::hasColumn('habitats', 'min_lvl_3'));
    }

    public function test_teams_table_exists_and_has_user_id(): void
    {
        $this->assertTrue(Schema::hasTable('teams'));
        $this->assertTrue(Schema::hasColumn('teams', 'id'));
        $this->assertTrue(Schema::hasColumn('teams', 'user_id'));
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

    public function test_pokedex_table_has_unique_constraint_on_user_and_pokemon(): void
    {
        $indexes = Schema::getIndexes('pokedex');
        $uniqueOnUserPokemon = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true
                && in_array('user_id', $index['columns'], true)
                && in_array('pokemon_id', $index['columns'], true);
        });
        $uniqueOnPokemonAlone = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true
                && $index['columns'] === ['pokemon_id'];
        });

        $this->assertTrue($uniqueOnUserPokemon, 'pokedex table should have a unique index on (user_id, pokemon_id)');
        $this->assertFalse($uniqueOnPokemonAlone, 'pokedex table should NOT keep the single-player unique on pokemon_id');
    }

    public function test_evolution_chains_table_no_existe(): void
    {
        // La tabla evolution_chains se eliminó (bug 23503): la agrupación de familias
        // vive en pokemon.evolution_chain_id (columna, sin FK).
        $this->assertFalse(Schema::hasTable('evolution_chains'));
    }

    public function test_player_inventory_table_has_unique_constraint_on_user_and_item_key(): void
    {
        $indexes = Schema::getIndexes('player_inventory');
        $uniqueOnUserItem = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true
                && in_array('user_id', $index['columns'], true)
                && in_array('item_key', $index['columns'], true);
        });

        $this->assertTrue($uniqueOnUserItem, 'player_inventory table should have a unique index on (user_id, item_key)');
    }

    public function test_users_has_experiencia_column_and_email_nullable_and_name_unique(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumn('users', 'experiencia'));

        $columns = collect(Schema::getColumns('users'))->keyBy('name');
        $this->assertTrue($columns['email']['nullable'], 'users.email debe ser nullable');
        $this->assertFalse($columns['name']['nullable'], 'users.name debe seguir siendo NOT NULL');

        $nameUnique = collect(Schema::getIndexes('users'))->contains(function ($index) {
            return $index['unique'] === true && $index['columns'] === ['name'];
        });
        $this->assertTrue($nameUnique, 'users.name debe tener índice unique');
    }

    public function test_reclutados_tiene_user_id_not_null(): void
    {
        $this->assertTrue(Schema::hasColumn('reclutados', 'user_id'));
        $columns = collect(Schema::getColumns('reclutados'))->keyBy('name');
        $this->assertFalse($columns['user_id']['nullable'], 'reclutados.user_id debe ser NOT NULL');
    }

    public function test_reclutados_exp_es_jsonb_en_postgres_o_text_en_sqlite(): void
    {
        // SQLite no tiene jsonb: el grammar lo traduce a TEXT (verificado en SQLiteGrammar).
        $columns = collect(Schema::getColumns('reclutados'))->keyBy('name');
        $this->assertContains($columns['exp']['type'], ['jsonb', 'text'], 'reclutados.exp debe ser jsonb (text en SQLite)');
    }

    public function test_habitats_tiene_peligro_con_default_uno(): void
    {
        $columns = collect(Schema::getColumns('habitats'))->keyBy('name');
        $this->assertArrayHasKey('peligro', $columns);
        // pgsql devuelve `'1'::smallint`; sqlite `1`. Se verifica el literal numérico.
        $this->assertMatchesRegularExpression('/\b1\b/', (string) $columns['peligro']['default'], 'habitats.peligro debe tener default 1');
    }

    public function test_exploraciones_activas_eventos_es_jsonb_en_postgres(): void
    {
        $columns = collect(Schema::getColumns('exploraciones_activas'))->keyBy('name');
        $this->assertContains($columns['eventos']['type'], ['jsonb', 'text', 'json'], 'eventos debe ser jsonb (json/text en SQLite)');
    }

    public function test_behavior_acepta_rastreador_y_rechaza_soporte(): void
    {
        $user = \App\Models\User::factory()->create();
        $team = \App\Models\Team::create(['name' => 'Equipo Behavior', 'user_id' => $user->id]);
        $pokemon = \App\Models\Pokemon::create([
            'id' => 9999,
            'name' => 'behavior-test',
            'species_id' => 9999,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);
        $reclutado = \App\Models\Reclutado::create([
            'user_id' => $user->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Behavior',
            'exp' => ['total' => 0],
        ]);

        // RASTREADOR (nuevo rol) es válido tras la migración.
        \App\Models\TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'RASTREADOR',
        ]);

        // SOPORTE ya no es un valor válido del enum (check constraint / sqlite).
        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\TeamMember::create([
            'team_id' => $team->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 2,
            'behavior' => 'SOPORTE',
        ]);
    }
}
