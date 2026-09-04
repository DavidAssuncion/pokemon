<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Favorito;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoritoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    private function crearReclutado(): Reclutado
    {
        $pokemon = Pokemon::create([
            'id' => 25,
            'name' => 'Pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
        ]);

        return Reclutado::create([
            'user_id' => $this->usuario->id,
            'nombre' => 'Pika',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100],
        ]);
    }

    private function crearHabitat(int $id = 1): Habitat
    {
        $province = Province::create(['id' => 100 + $id, 'name' => 'Prov-'.$id]);

        return Habitat::create(['id' => $id, 'name' => 'Hab-'.$id, 'province_id' => $province->id, 'peligro' => 1]);
    }

    #[Test]
    public function test_tabla_favoritos_existe_con_columnas_correctas(): void
    {
        $this->assertTrue(Schema::hasTable('favoritos'));
        $this->assertTrue(Schema::hasColumn('favoritos', 'id'));
        $this->assertTrue(Schema::hasColumn('favoritos', 'user_id'));
        $this->assertTrue(Schema::hasColumn('favoritos', 'reclutado_id'));
        $this->assertTrue(Schema::hasColumn('favoritos', 'habitat_id'));
        $this->assertTrue(Schema::hasColumn('favoritos', 'created_at'));
    }

    #[Test]
    public function test_reclutados_no_tiene_columna_favorito(): void
    {
        $this->assertFalse(Schema::hasColumn('reclutados', 'favorito'));
    }

    #[Test]
    public function test_habitat_favoritos_no_existe(): void
    {
        $this->assertFalse(Schema::hasTable('habitat_favoritos'));
    }

    #[Test]
    public function test_toggle_crea_favorito_global(): void
    {
        $reclutado = $this->crearReclutado();

        $resultado = Favorito::toggle($this->usuario->id, $reclutado->id);

        $this->assertTrue($resultado);
        $this->assertDatabaseHas('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => null,
        ]);
    }

    #[Test]
    public function test_toggle_elimina_favorito_global(): void
    {
        $reclutado = $this->crearReclutado();
        Favorito::toggle($this->usuario->id, $reclutado->id);

        $resultado = Favorito::toggle($this->usuario->id, $reclutado->id);

        $this->assertFalse($resultado);
        $this->assertDatabaseMissing('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
        ]);
    }

    #[Test]
    public function test_toggle_crea_favorito_para_habitat(): void
    {
        $reclutado = $this->crearReclutado();
        $habitat = $this->crearHabitat();

        $resultado = Favorito::toggle($this->usuario->id, $reclutado->id, $habitat->id);

        $this->assertTrue($resultado);
        $this->assertDatabaseHas('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);
    }

    #[Test]
    public function test_toggle_elimina_favorito_para_habitat(): void
    {
        $reclutado = $this->crearReclutado();
        $habitat = $this->crearHabitat();
        Favorito::toggle($this->usuario->id, $reclutado->id, $habitat->id);

        $resultado = Favorito::toggle($this->usuario->id, $reclutado->id, $habitat->id);

        $this->assertFalse($resultado);
        $this->assertDatabaseMissing('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);
    }

    #[Test]
    public function test_scope_globales_solo_devuelve_favoritos_sin_habitat(): void
    {
        $reclutado1 = $this->crearReclutado();
        $pokemon2 = Pokemon::create([
            'id' => 26, 'name' => 'Raichu', 'species_id' => 26,
            'capture_rate' => 75, 'base_experience' => 218, 'height' => 8, 'weight' => 30,
        ]);
        $reclutado2 = Reclutado::create([
            'user_id' => $this->usuario->id, 'nombre' => 'Rai', 'pokemon_id' => $pokemon2->id, 'exp' => ['total' => 200],
        ]);
        $habitat = $this->crearHabitat();

        Favorito::toggle($this->usuario->id, $reclutado1->id); // global
        Favorito::toggle($this->usuario->id, $reclutado2->id, $habitat->id); // habitat

        $globales = Favorito::globales()->where('user_id', $this->usuario->id)->get();

        $this->assertCount(1, $globales);
        $this->assertSame($reclutado1->id, $globales->first()->reclutado_id);
    }

    #[Test]
    public function test_scope_para_habitat_solo_devuelve_favoritos_de_ese_habitat(): void
    {
        $reclutado1 = $this->crearReclutado();
        $pokemon2 = Pokemon::create([
            'id' => 26, 'name' => 'Raichu', 'species_id' => 26,
            'capture_rate' => 75, 'base_experience' => 218, 'height' => 8, 'weight' => 30,
        ]);
        $reclutado2 = Reclutado::create([
            'user_id' => $this->usuario->id, 'nombre' => 'Rai', 'pokemon_id' => $pokemon2->id, 'exp' => ['total' => 200],
        ]);
        $habitat1 = $this->crearHabitat(1);
        $habitat2 = $this->crearHabitat(2);

        Favorito::toggle($this->usuario->id, $reclutado1->id, $habitat1->id);
        Favorito::toggle($this->usuario->id, $reclutado2->id, $habitat2->id);

        $delHabitat1 = Favorito::paraHabitat($habitat1->id)->where('user_id', $this->usuario->id)->get();

        $this->assertCount(1, $delHabitat1);
        $this->assertSame($reclutado1->id, $delHabitat1->first()->reclutado_id);
    }

    #[Test]
    public function test_count_globales_cuenta_solo_favoritos_globales(): void
    {
        $reclutado1 = $this->crearReclutado();
        $pokemon2 = Pokemon::create([
            'id' => 26, 'name' => 'Raichu', 'species_id' => 26,
            'capture_rate' => 75, 'base_experience' => 218, 'height' => 8, 'weight' => 30,
        ]);
        $reclutado2 = Reclutado::create([
            'user_id' => $this->usuario->id, 'nombre' => 'Rai', 'pokemon_id' => $pokemon2->id, 'exp' => ['total' => 200],
        ]);
        $habitat = $this->crearHabitat();

        Favorito::toggle($this->usuario->id, $reclutado1->id); // global
        Favorito::toggle($this->usuario->id, $reclutado2->id, $habitat->id); // habitat

        $this->assertSame(1, Favorito::countGlobales($this->usuario->id));
    }

    #[Test]
    public function test_count_para_habitat_cuenta_solo_favoritos_de_ese_habitat(): void
    {
        $reclutado1 = $this->crearReclutado();
        $pokemon2 = Pokemon::create([
            'id' => 26, 'name' => 'Raichu', 'species_id' => 26,
            'capture_rate' => 75, 'base_experience' => 218, 'height' => 8, 'weight' => 30,
        ]);
        $reclutado2 = Reclutado::create([
            'user_id' => $this->usuario->id, 'nombre' => 'Rai', 'pokemon_id' => $pokemon2->id, 'exp' => ['total' => 200],
        ]);
        $habitat1 = $this->crearHabitat(1);
        $habitat2 = $this->crearHabitat(2);

        Favorito::toggle($this->usuario->id, $reclutado1->id, $habitat1->id);
        Favorito::toggle($this->usuario->id, $reclutado2->id, $habitat2->id);

        // Los globales (habitat_id null) no cuentan para ningún hábitat.
        Favorito::toggle($this->usuario->id, $reclutado1->id);

        $this->assertSame(1, Favorito::countParaHabitat($this->usuario->id, $habitat1->id));
        $this->assertSame(1, Favorito::countParaHabitat($this->usuario->id, $habitat2->id));
    }

    #[Test]
    public function test_unique_constraint_prevene_duplicados_por_habitat(): void
    {
        $reclutado = $this->crearReclutado();
        $habitat = $this->crearHabitat();

        \Illuminate\Support\Facades\DB::table('favoritos')->insert([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('favoritos')->insert([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);
    }
}
