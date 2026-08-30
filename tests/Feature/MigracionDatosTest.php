<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigracionDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_vuelca_caramelos_a_player_inventory_y_exp_tipo_a_exp_tipos(): void
    {
        // Reversa las migraciones posteriores a las de volcado de caramelos/exp_tipo
        // (000008/000009): las 3 de la iteración expediciones (2026_08_30_*) + las 4
        // originales posteriores (000010/000011/000009/000008) = 7 pasos, para poder
        // insertar los datos "viejos" antes de volver a migrar.
        Artisan::call('migrate:rollback', ['--step' => 7]);

        $usuario = User::factory()->create();
        $pokemon = $this->createPokemon();
        $reclutadoId = DB::table('reclutados')->insertGetId([
            'nombre' => 'Pikachu',
            'pokemon_id' => $pokemon->id,
            'user_id' => $usuario->id,
            'exp' => json_encode(['total' => 500]),
            'es_shiny' => false,
            'obj_equipados' => json_encode([]),
            'movimientos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('caramelos')->insert(['evolution_chain_id' => 51, 'cantidad' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('caramelos_ev')->insert(['stat' => 6, 'cantidad' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('caramelos_tipo')->insert(['tipo' => 'Eléctrico', 'cantidad' => 7, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('reclutados_exp_tipo')->insert([
            ['reclutado_id' => $reclutadoId, 'tipo' => 'Eléctrico', 'cantidad' => 300, 'created_at' => now(), 'updated_at' => now()],
            ['reclutado_id' => $reclutadoId, 'tipo' => 'Normal', 'cantidad' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Artisan::call('migrate');

        // El usuario legacy se crea SOLO porque hay filas que migrar.
        $legacy = DB::table('users')->where('email', 'legacy@local')->first();
        $this->assertNotNull($legacy, 'debe crearse el usuario legacy para volcar los datos');
        $this->assertSame('Legacy', $legacy->name);
        $this->assertNotSame($usuario->id, $legacy->id);

        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $legacy->id,
            'item_key' => 'familia:51',
            'cantidad' => 10,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $legacy->id,
            'item_key' => 'ev:6',
            'cantidad' => 3,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $legacy->id,
            'item_key' => 'tipo:electrico',
            'cantidad' => 7,
        ]);

        $exp = json_decode((string) DB::table('reclutados')->where('id', $reclutadoId)->value('exp'), true);
        $this->assertSame(500, $exp['total'], 'el total de exp no debe cambiar');
        // El orden de las claves JSON no está garantizado (SELECT sin ORDER BY): assert por clave.
        $this->assertSame(300, $exp['tipos']['Eléctrico']);
        $this->assertSame(100, $exp['tipos']['Normal']);
        $this->assertCount(2, $exp['tipos']);

        $this->assertFalse(Schema::hasTable('caramelos'));
        $this->assertFalse(Schema::hasTable('caramelos_ev'));
        $this->assertFalse(Schema::hasTable('caramelos_tipo'));
        $this->assertFalse(Schema::hasTable('reclutados_exp_tipo'));
    }

    public function test_no_se_crea_usuario_legacy_sin_datos_que_migrar(): void
    {
        $this->assertNull(DB::table('users')->where('email', 'legacy@local')->first());
    }

    public function test_down_restaura_caramelos_y_reclutados_exp_tipo_best_effort(): void
    {
        // 7 pasos: las 3 migraciones de la iteración expediciones + las 4 de
        // índices/min_lvl/volcado que recrean las tablas legacy.
        Artisan::call('migrate:rollback', ['--step' => 7]);

        $usuario = User::factory()->create();
        $pokemon = $this->createPokemon();
        $reclutadoId = DB::table('reclutados')->insertGetId([
            'nombre' => 'Pikachu',
            'pokemon_id' => $pokemon->id,
            'user_id' => $usuario->id,
            'exp' => json_encode(['total' => 500]),
            'es_shiny' => false,
            'obj_equipados' => json_encode([]),
            'movimientos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('caramelos')->insert(['evolution_chain_id' => 51, 'cantidad' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('reclutados_exp_tipo')->insert(['reclutado_id' => $reclutadoId, 'tipo' => 'Eléctrico', 'cantidad' => 300, 'created_at' => now(), 'updated_at' => now()]);

        Artisan::call('migrate');
        Artisan::call('migrate:rollback', ['--step' => 7]);

        $this->assertTrue(Schema::hasTable('caramelos'));
        $this->assertTrue(Schema::hasTable('reclutados_exp_tipo'));
        $this->assertDatabaseHas('caramelos', ['evolution_chain_id' => 51, 'cantidad' => 10]);
        $this->assertDatabaseHas('reclutados_exp_tipo', ['reclutado_id' => $reclutadoId, 'tipo' => 'Eléctrico', 'cantidad' => 300]);
    }

    private function createPokemon(): Pokemon
    {
        return Pokemon::create([
            'id' => 25,
            'name' => 'Pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
            'evolution_chain_id' => 51,
        ]);
    }
}
