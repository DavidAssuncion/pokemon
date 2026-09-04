<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigracionExploracionIndividualTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_exploraciones_usa_reclutado_id_en_vez_de_equipo_id(): void
    {
        // After all migrations, exploraciones_activas has reclutado_id, not equipo_id
        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'reclutado_id'));
        $this->assertFalse(Schema::hasColumn('exploraciones_activas', 'equipo_id'));
        $this->assertFalse(Schema::hasColumn('reclutados', 'favorito'));
        $this->assertTrue(Schema::hasTable('favoritos'));
        $this->assertFalse(Schema::hasTable('habitat_favoritos'));
    }

    private function crearHabitat(): Habitat
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);

        return Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => $province->id]);
    }

    private function crearPokemonBasico(): Pokemon
    {
        return Pokemon::create([
            'id' => 25,
            'name' => 'Pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
        ]);
    }

    #[Test]
    public function test_backfill_reclutado_id_desde_primer_miembro_del_equipo(): void
    {
        // Reversa la última migración (change_equipo_id...) para poder insertar
        // datos "viejos" con equipo_id y volver a migrar.
        Artisan::call('migrate:rollback', ['--step' => 3]);

        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'equipo_id'));

        $usuario = User::factory()->create();
        $equipo = Team::create(['name' => 'Equipo', 'user_id' => $usuario->id]);
        $habitat = $this->crearHabitat();
        $pokemon = $this->crearPokemonBasico();
        $reclutado1 = Reclutado::create([
            'user_id' => $usuario->id,
            'nombre' => 'Primero',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100],
        ]);
        $reclutado2 = Reclutado::create([
            'user_id' => $usuario->id,
            'nombre' => 'Segundo',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 200],
        ]);
        TeamMember::create(['team_id' => $equipo->id, 'pokemon_id' => $reclutado2->id, 'slot' => 1]);
        TeamMember::create(['team_id' => $equipo->id, 'pokemon_id' => $reclutado1->id, 'slot' => 2]);

        $exploracionId = DB::table('exploraciones_activas')->insertGetId([
            'user_id' => $usuario->id,
            'equipo_id' => $equipo->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'indefinido' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('migrate');

        // El backfill toma el primer team_member por id → reclutado2
        $reclutadoId = DB::table('exploraciones_activas')->where('id', $exploracionId)->value('reclutado_id');
        $this->assertSame($reclutado2->id, (int) $reclutadoId);
        $this->assertFalse(Schema::hasColumn('exploraciones_activas', 'equipo_id'));
    }

    #[Test]
    public function test_down_restaura_equipo_id_y_backfill_desde_reclutado_id(): void
    {
        $usuario = User::factory()->create();
        $equipo = Team::create(['name' => 'Equipo', 'user_id' => $usuario->id]);
        $habitat = $this->crearHabitat();
        $pokemon = $this->crearPokemonBasico();
        $reclutado = Reclutado::create([
            'user_id' => $usuario->id,
            'nombre' => 'Pika',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100],
        ]);
        TeamMember::create(['team_id' => $equipo->id, 'pokemon_id' => $reclutado->id, 'slot' => 1]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'indefinido' => true,
        ]);

        Artisan::call('migrate:rollback', ['--step' => 1]);

        $this->assertTrue(Schema::hasColumn('exploraciones_activas', 'equipo_id'));
        $this->assertFalse(Schema::hasColumn('exploraciones_activas', 'reclutado_id'));

        $equipoId = DB::table('exploraciones_activas')->where('id', $exploracion->id)->value('equipo_id');
        $this->assertSame($equipo->id, (int) $equipoId);
    }
}
